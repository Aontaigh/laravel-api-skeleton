<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Sessions;

use App\Actions\Sessions\RevokeWebSessionAction;
use App\Http\Controllers\Sessions\DestroySessionController;
use App\Http\Requests\Sessions\DestroySessionRequest;
use App\Models\User;
use App\Models\WebSession;
use App\Policies\WebSessionPolicy;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for revoking a registered web session by id.
 */
#[CoversClass(DestroySessionController::class)]
#[CoversClass(DestroySessionRequest::class)]
#[CoversClass(RevokeWebSessionAction::class)]
#[CoversClass(WebSessionPolicy::class)]
#[CoversClass(ApiResponse::class)]
final class DestroySessionControllerTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Seed permissions for session revoke authorisation.
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

    /*
     * Mutation Tests
     * --------------
     */

    /**
     * Revoke one of the caller's own Web Sessions.
     */
    #[Test]
    public function it_revokes_one_of_the_callers_own_sessions(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();
        $webSession = WebSession::factory()->for($user)->create([
            'session_id' => 'session-to-revoke',
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)
            ->deleteJson("/api/sessions/{$webSession->id}");

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Session Revoked Successfully');
        $this->assertNotNull($webSession->fresh()?->revoked_at);
    }

    /**
     * Allow an Admin to revoke another User's Web Session.
     */
    #[Test]
    public function it_allows_an_admin_to_revoke_another_users_session(): void
    {
        // Arrange

        $admin = User::factory()->admin()->create();
        $otherUser = User::factory()->user()->create();
        $webSession = WebSession::factory()->for($otherUser)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)
            ->deleteJson("/api/sessions/{$webSession->id}");

        // Assert

        $response->assertOk();
        $this->assertNotNull($webSession->fresh()?->revoked_at);
    }

    /*
     * Not Found Tests
     * ---------------
     */

    /**
     * Return 404 when revoking another User's session without `sessions.revoke-any`.
     */
    #[Test]
    public function it_returns_not_found_when_revoking_another_users_session_without_revoke_any(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();
        $foreignSession = WebSession::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)
            ->deleteJson("/api/sessions/{$foreignSession->id}");

        // Assert

        $response->assertNotFound();
    }

    /*
     * Authorization Tests
     * -------------------
     */

    /**
     * Deny viewers without the revoke-own permission.
     */
    #[Test]
    public function it_denies_viewers_without_the_revoke_own_permission(): void
    {
        // Arrange

        /** @var User $roleless */
        $roleless = User::factory()->create();
        $webSession = WebSession::factory()->for($roleless)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($roleless)
            ->deleteJson("/api/sessions/{$webSession->id}");

        // Assert

        $response->assertForbidden();
    }

    /**
     * Deny service accounts from revoking Web Sessions.
     */
    #[Test]
    public function it_denies_service_accounts(): void
    {
        // Arrange

        $service = User::factory()->service()->create();
        $webSession = WebSession::factory()->for($service)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($service)
            ->deleteJson("/api/sessions/{$webSession->id}");

        // Assert

        $response->assertForbidden();
    }

    /**
     * Deny unauthenticated requests.
     */
    #[Test]
    public function it_denies_unauthenticated_requests(): void
    {
        // Arrange

        $webSession = WebSession::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->deleteJson("/api/sessions/{$webSession->id}");

        // Assert

        $response->assertUnauthorized();
    }
}
