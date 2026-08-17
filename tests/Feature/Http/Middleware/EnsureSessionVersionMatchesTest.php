<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\EnsureSessionVersionMatches;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
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
     * Turn away a session with no version stamp or a superseded stamp.
     */
    #[Test]
    public function it_rejects_an_unstamped_session(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this
            ->actingAs($user)
            ->withHeader('Origin', config()->string('app.url'))
            ->getJson('/api/users');

        // Assert

        $this->assertApiErrorEnvelope($response, 401, 'Session Expired');
    }

    /**
     * Pass through when the stamped version matches the User's current version.
     */
    #[Test]
    public function it_passes_when_the_session_version_matches(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->admin()->create();

        $user->rotateSessions();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this
            ->actingAs($user)
            ->withSession(['session_version' => $user->session_version])
            ->withHeader('Origin', config()->string('app.url'))
            ->getJson('/api/users');

        // Assert

        $response->assertOk();
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
     * Log out, invalidate, and rotate the CSRF token when the stamped version is stale.
     *
     * Mirrors the session teardown in LogoutUserAction so a superseded session cannot
     * keep the User authenticated or reuse the old CSRF token on the next request.
     */
    #[Test]
    public function it_logs_out_invalidates_and_regenerates_the_csrf_token_when_the_version_is_stale(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->admin()->create();

        $staleCsrfToken = 'stale-csrf-token';

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this
            ->actingAs($user)
            ->withSession([
                'session_version' => $user->session_version - 1,
                '_token' => $staleCsrfToken,
            ])
            ->withHeader('Origin', config()->string('app.url'))
            ->getJson('/api/users');

        // Assert

        $this->assertApiErrorEnvelope($response, 401, 'Session Expired');

        $regeneratedCsrfToken = session()->token();

        $this->assertNotSame($staleCsrfToken, $regeneratedCsrfToken);
        $this->assertNotEmpty($regeneratedCsrfToken);

        /*
         * actingAs() leaves the User on the auth singleton after the request
         * returns, so prove logout with a follow-up call that only carries the
         * invalidated session forward.
         */

        Auth::forgetGuards();

        /** @var TestResponse<JsonResponse> $followUp */
        $followUp = $this
            ->withHeader('Origin', config()->string('app.url'))
            ->getJson('/api/users');

        $followUp->assertUnauthorized();
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

    /**
     * Leave Bearer-token callers untouched even when a stateful Origin binds a session.
     */
    #[Test]
    public function it_ignores_bearer_token_callers_when_a_stateful_origin_binds_a_session(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->admin()->create();

        $token = $user->createToken('device')->plainTextToken;

        $user->rotateSessions();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this
            ->withToken($token)
            ->withHeader('Origin', config()->string('app.url'))
            ->getJson('/api/users');

        // Assert

        $response->assertOk();
    }
}
