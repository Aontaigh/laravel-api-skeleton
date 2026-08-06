<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Users;

use App\Actions\Users\SoftDeleteUserAction;
use App\Enums\RoleName;
use App\Http\Controllers\Users\DestroyUserController;
use App\Http\Requests\Users\DestroyUserRequest;
use App\Models\Team;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the User soft-delete endpoint.
 */
#[CoversClass(DestroyUserController::class)]
#[CoversClass(DestroyUserRequest::class)]
#[CoversClass(SoftDeleteUserAction::class)]
#[CoversClass(UserPolicy::class)]
#[CoversClass(ApiResponse::class)]
final class DestroyUserControllerTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @var Team the Team the manager viewer belongs to */
    private Team $team;

    /** @var User a manager viewer with `users.delete` */
    private User $manager;

    /** @var User a User on the manager's Team */
    private User $teamMember;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Create the Team, manager, and team member shared by every test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->team = Team::factory()->create();

        $this->manager = User::factory()->for($this->team)->manager()->create([
            'name' => 'Team Manager',
            'email' => 'manager@example.com',
        ]);

        $this->teamMember = User::factory()->for($this->team)->user()->create([
            'name' => 'Team Member',
            'email' => 'member@example.com',
        ]);
    }

    /*
     * Delete Tests
     * ------------
     */

    /**
     * Soft-delete a User on the manager's Team.
     */
    #[Test]
    public function it_soft_deletes_a_user_on_the_managers_team(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->manager)->deleteJson(
            "/api/users/{$this->teamMember->id}",
        );

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'User Deleted Successfully');
        $this->assertSoftDeleted('users', ['id' => $this->teamMember->id]);
    }

    /**
     * Allow an admin to soft-delete a User on another Team.
     */
    #[Test]
    public function it_allows_an_admin_to_soft_delete_a_user_on_another_team(): void
    {
        // Arrange

        /** @var Team $otherTeam */
        $otherTeam = Team::factory()->create();

        /** @var User $admin */
        $admin = User::factory()->for($this->team)->admin()->create();

        /** @var User $otherUser */
        $otherUser = User::factory()->for($otherTeam)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->deleteJson("/api/users/{$otherUser->id}");

        // Assert

        $response->assertOk();
        $this->assertSoftDeleted('users', ['id' => $otherUser->id]);
    }

    /**
     * Exclude soft-deleted Users from the index.
     */
    #[Test]
    public function it_excludes_soft_deleted_users_from_the_index(): void
    {
        // Arrange

        $this->actingAs($this->manager)->deleteJson("/api/users/{$this->teamMember->id}");

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->manager)->getJson('/api/users');

        // Assert

        $response->assertOk();

        /** @var list<array<string, mixed>> $rows */
        $rows = $response->json('data');
        $ids = array_column($rows, 'id');

        $this->assertNotContains($this->teamMember->id, $ids);
    }

    /**
     * Return not found when showing a soft-deleted User.
     */
    #[Test]
    public function it_returns_not_found_when_showing_a_soft_deleted_user(): void
    {
        // Arrange

        $this->actingAs($this->manager)->deleteJson("/api/users/{$this->teamMember->id}");

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->manager)->getJson(
            "/api/users/{$this->teamMember->id}",
        );

        // Assert

        $response->assertNotFound();
    }

    /*
     * Authorisation Tests
     * -------------------
     */

    /**
     * Deny deleting a User on another Team.
     */
    #[Test]
    public function it_denies_deleting_a_user_on_another_team(): void
    {
        // Arrange

        /** @var Team $otherTeam */
        $otherTeam = Team::factory()->create();

        /** @var User $otherUser */
        $otherUser = User::factory()->for($otherTeam)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->manager)->deleteJson("/api/users/{$otherUser->id}");

        // Assert

        $response->assertForbidden();
        $this->assertNotSoftDeleted('users', ['id' => $otherUser->id]);
    }

    /**
     * Deny deleting the caller's own account.
     */
    #[Test]
    public function it_denies_deleting_the_callers_own_account(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->manager)->deleteJson(
            "/api/users/{$this->manager->id}",
        );

        // Assert

        $response->assertForbidden();
        $this->assertNotSoftDeleted('users', ['id' => $this->manager->id]);
    }

    /**
     * Authorise delete according to the Role matrix.
     */
    /**
     * Authorise delete according to the Role matrix.
     */
    #[Test]
    /**
     * Authorise delete according to the Role matrix.
     */
    #[DataProvider('roleAuthorisationProvider')]
    public function it_authorises_delete_according_to_the_role_matrix(string $role, bool $canDelete): void
    {
        // Arrange

        $factory = match ($role) {
            RoleName::Admin->value => User::factory()->for($this->team)->admin(),
            RoleName::Manager->value => User::factory()->for($this->team)->manager(),
            RoleName::User->value => User::factory()->for($this->team)->user(),
            default => throw new InvalidArgumentException("Unmapped Role: {$role}"),
        };

        /** @var User $viewer */
        $viewer = $factory->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($viewer)->deleteJson("/api/users/{$this->teamMember->id}");

        // Assert

        if ($canDelete) {
            $response->assertOk();
            $this->assertSoftDeleted('users', ['id' => $this->teamMember->id]);
        } else {
            $response->assertForbidden();
            $this->assertNotSoftDeleted('users', ['id' => $this->teamMember->id]);
        }
    }

    /**
     * Return not found for a nonexistent User.
     */
    #[Test]
    public function it_returns_not_found_for_a_nonexistent_user(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->manager)->deleteJson('/api/users/999999');

        // Assert

        $response->assertNotFound();
    }

    /**
     * Deny unauthenticated requests.
     */
    #[Test]
    public function it_denies_unauthenticated_requests(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->deleteJson("/api/users/{$this->teamMember->id}");

        // Assert

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Spatie role names mapped to whether that role may delete a User.
     *
     * @return array<string, array{0: string, 1: bool}> case name mapped to [role, canDelete]
     */
    public static function roleAuthorisationProvider(): array
    {
        return [
            'Admin can delete' => [RoleName::Admin->value, true],
            'Manager can delete' => [RoleName::Manager->value, true],
            'User cannot delete' => [RoleName::User->value, false],
        ];
    }
}
