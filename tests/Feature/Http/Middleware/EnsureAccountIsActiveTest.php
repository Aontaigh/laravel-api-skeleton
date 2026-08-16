<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AssertsApiEnvelope;
use Tests\TestCase;

/**
 * Feature tests for the account-suspension gate on authenticated routes.
 */
#[CoversClass(EnsureAccountIsActive::class)]
final class EnsureAccountIsActiveTest extends TestCase
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
     * Turn away a suspended Bearer-token caller with a 403.
     */
    #[Test]
    public function it_rejects_a_suspended_token_caller(): void
    {
        // Arrange

        $user = User::factory()->admin()->suspended()->create();

        Sanctum::actingAs($user, ['users.list']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/users');

        // Assert

        $this->assertApiErrorEnvelope($response, 403, 'Account Suspended');
    }

    /**
     * Turn away a suspended cookie-session caller with a 403.
     */
    #[Test]
    public function it_rejects_a_suspended_session_caller(): void
    {
        // Arrange

        $user = User::factory()->admin()->suspended()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->getJson('/api/users');

        // Assert

        $this->assertApiErrorEnvelope($response, 403, 'Account Suspended');
    }

    /**
     * Let an active caller reach the gated route.
     */
    #[Test]
    public function it_allows_an_active_caller(): void
    {
        // Arrange

        $user = User::factory()->admin()->create();

        Sanctum::actingAs($user, ['users.list']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/users');

        // Assert

        $response->assertOk();
    }

    /**
     * Restore access once the suspension is lifted.
     */
    #[Test]
    public function it_allows_a_caller_once_unsuspended(): void
    {
        // Arrange

        $user = User::factory()->admin()->suspended()->create();

        Sanctum::actingAs($user, ['users.list']);

        // Act

        $user->forceFill(['suspended_at' => null])->save();

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/users');

        // Assert

        $response->assertOk();
    }
}
