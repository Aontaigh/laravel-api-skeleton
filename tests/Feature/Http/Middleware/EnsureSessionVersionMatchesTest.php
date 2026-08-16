<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\EnsureSessionVersionMatches;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AssertsApiEnvelope;
use Tests\TestCase;

/**
 * Feature tests for the session-version gate on authenticated routes.
 *
 * The gate turns away any web session whose stamped `session_version` no longer
 * matches the User's current version — the driver-agnostic "log out everywhere"
 * mechanism. Bearer-token clients carry no session, so they are never gated.
 */
#[CoversClass(EnsureSessionVersionMatches::class)]
final class EnsureSessionVersionMatchesTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AssertsApiEnvelope;
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Seed the roles an authenticated caller needs to reach a gated route.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Stamp an unstamped session with the current version and let it through.
     */
    #[Test]
    public function it_stamps_an_unstamped_session_and_passes(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->getJson('/api/users');

        // Assert

        $response->assertOk();
        $this->assertSame($user->session_version, session()->get('session_version'));
    }

    /**
     * Turn away a session whose stamped version is older than the User's.
     *
     * Pre-seeding the stale stamp via withSession() mirrors the state a browser
     * arrives in after a force-logout bumped the version server-side: the
     * session still holds the old number while the User row has moved on.
     */
    #[Test]
    public function it_rejects_a_session_after_the_version_is_bumped(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->admin()->create();

        // Act

        /*
         * Present a session stamped with a superseded version. The Origin header
         * puts the request through Sanctum's stateful middleware so a session is
         * actually bound (a bare API request carries none).
         */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this
            ->actingAs($user)
            ->withSession(['session_version' => $user->session_version - 1])
            ->withHeader('Origin', config()->string('app.url'))
            ->getJson('/api/users');

        // Assert

        $this->assertApiErrorEnvelope($response, 401, 'Session Expired');
    }

    /**
     * Leave a Bearer-token caller untouched: no session means nothing to gate.
     */
    #[Test]
    public function it_ignores_bearer_token_callers(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->admin()->create();

        \Laravel\Sanctum\Sanctum::actingAs($user, ['users.list']);

        // Act

        /*
         * Bump the version; a stateless caller must not be turned away.
         */

        $user->rotateSessions();

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/users');

        // Assert

        $response->assertOk();
    }
}
