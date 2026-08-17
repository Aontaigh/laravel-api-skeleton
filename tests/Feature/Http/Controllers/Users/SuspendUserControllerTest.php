<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Users;

use App\Actions\Auth\LogoutUserAction;
use App\Actions\Users\SuspendUserAction;
use App\Http\Controllers\Users\SuspendUserController;
use App\Http\Requests\Users\SuspendUserRequest;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the User suspension endpoint.
 */
#[CoversClass(SuspendUserController::class)]
#[CoversClass(SuspendUserRequest::class)]
#[CoversClass(SuspendUserAction::class)]
#[CoversClass(LogoutUserAction::class)]
#[CoversClass(UserPolicy::class)]
#[CoversClass(ApiResponse::class)]
final class SuspendUserControllerTest extends TestCase
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
     * Seed permissions for the Spatie role gate.
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
     * Suspend Tests
     * -------------
     */

    /**
     * Suspend a User as an Admin.
     */
    #[Test]
    public function it_suspends_a_user_as_an_admin(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $target */
        $target = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson("/api/users/{$target->id}/suspend");

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'User Suspended Successfully');

        $this->assertNotNull($target->fresh()?->suspended_at);
    }

    /**
     * Turn the suspended User away on their next authenticated request.
     */
    #[Test]
    public function it_blocks_the_suspended_user_on_authenticated_routes(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $target */
        $target = User::factory()->user()->create();

        $this->actingAs($admin)->postJson("/api/users/{$target->id}/suspend")->assertOk();

        $target->refresh();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($target)->getJson('/api/me');

        // Assert

        $this->assertApiErrorEnvelope($response, 403, 'Account Suspended');
    }

    /**
     * Revoke every Personal Access Token so a suspended User cannot keep using Bearer auth.
     */
    #[Test]
    public function it_revokes_personal_access_tokens_for_the_suspended_user(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $target */
        $target = User::factory()->user()->create();

        $token = $target->createToken('suspended-device');

        // Act

        $this->actingAs($admin)->postJson("/api/users/{$target->id}/suspend")->assertOk();

        // Assert

        $this->assertDatabaseCount('personal_access_tokens', 0);

        Auth::forgetGuards();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/tokens')
            ->assertUnauthorized();
    }

    /**
     * Reject an Admin from suspending their own account.
     */
    #[Test]
    public function it_rejects_an_admin_from_suspending_their_own_account(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson("/api/users/{$admin->id}/suspend");

        // Assert

        $response->assertForbidden();

        $this->assertNull($admin->fresh()?->suspended_at);
    }

    /*
     * Authorisation Tests
     * -------------------
     */

    /**
     * Deny managers without the `users.suspend` permission.
     */
    #[Test]
    public function it_denies_managers(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();

        /** @var User $target */
        $target = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($manager)->postJson("/api/users/{$target->id}/suspend");

        // Assert

        $response->assertForbidden();

        $this->assertNull($target->fresh()?->suspended_at);
    }

    /**
     * Deny regular users.
     */
    #[Test]
    public function it_denies_regular_users(): void
    {
        // Arrange

        /** @var User $viewer */
        $viewer = User::factory()->user()->create();

        /** @var User $target */
        $target = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($viewer)->postJson("/api/users/{$target->id}/suspend");

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

        /** @var User $target */
        $target = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson("/api/users/{$target->id}/suspend");

        // Assert

        $response->assertUnauthorized();
    }

    /**
     * Return not found for a nonexistent User.
     */
    #[Test]
    public function it_returns_not_found_for_a_nonexistent_user(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/users/999999/suspend');

        // Assert

        $response->assertNotFound();
    }
}
