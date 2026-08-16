<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Users;

use App\Actions\Users\UnsuspendUserAction;
use App\Http\Controllers\Users\UnsuspendUserController;
use App\Http\Requests\Users\UnsuspendUserRequest;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the User unsuspension endpoint.
 */
#[CoversClass(UnsuspendUserController::class)]
#[CoversClass(UnsuspendUserRequest::class)]
#[CoversClass(UnsuspendUserAction::class)]
#[CoversClass(UserPolicy::class)]
#[CoversClass(ApiResponse::class)]
final class UnsuspendUserControllerTest extends TestCase
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
     * Unsuspend Tests
     * ---------------
     */

    /**
     * Unsuspend a User as an Admin.
     */
    #[Test]
    public function it_unsuspends_a_user_as_an_admin(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $target */
        $target = User::factory()->user()->suspended()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson("/api/users/{$target->id}/unsuspend");

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'User Unsuspended Successfully');

        $this->assertNull($target->fresh()?->suspended_at);
    }

    /**
     * Restore the User's access once unsuspended.
     */
    #[Test]
    public function it_restores_access_once_unsuspended(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $target */
        $target = User::factory()->user()->suspended()->create();

        // Act

        $this->actingAs($admin)->postJson("/api/users/{$target->id}/unsuspend")->assertOk();

        $target->refresh();

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($target)->getJson('/api/me');

        // Assert

        $response->assertOk();
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
        $target = User::factory()->user()->suspended()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($manager)->postJson("/api/users/{$target->id}/unsuspend");

        // Assert

        $response->assertForbidden();

        $this->assertNotNull($target->fresh()?->suspended_at);
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
        $target = User::factory()->user()->suspended()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($viewer)->postJson("/api/users/{$target->id}/unsuspend");

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
        $target = User::factory()->user()->suspended()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson("/api/users/{$target->id}/unsuspend");

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
        $response = $this->actingAs($admin)->postJson('/api/users/999999/unsuspend');

        // Assert

        $response->assertNotFound();
    }
}
