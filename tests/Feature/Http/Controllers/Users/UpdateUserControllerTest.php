<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Users;

use App\Actions\Users\UpdateUserAction;
use App\DataTransferObjects\Users\UpdateUserData;
use App\Enums\RoleName;
use App\Http\Controllers\Users\UpdateUserController;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
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
use Tests\Concerns\AssertsApiEnvelope;
use Tests\TestCase;

/**
 * Feature tests for the User update endpoint.
 */
#[CoversClass(UpdateUserController::class)]
#[CoversClass(UpdateUserRequest::class)]
#[CoversClass(UpdateUserAction::class)]
#[CoversClass(UpdateUserData::class)]
#[CoversClass(UserPolicy::class)]
#[CoversClass(UserResource::class)]
#[CoversClass(ApiResponse::class)]
final class UpdateUserControllerTest extends TestCase
{
    use AssertsApiEnvelope;
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

    /** @var User a manager viewer with `users.update` */
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
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /*
     * Update Tests
     * ------------
     */

    /**
     * Update a User on the manager's Team.
     */
    #[Test]
    public function it_updates_a_user_on_the_managers_team(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->manager)->patchJson(
            "/api/users/{$this->teamMember->id}",
            ['name' => 'Renamed Member'],
        );

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'User Updated Successfully');
        $response->assertJsonPath('data.name', 'Renamed Member');
        $this->assertDatabaseHas('users', [
            'id' => $this->teamMember->id,
            'name' => 'Renamed Member',
        ]);
    }

    /**
     * Strip markup from the updated name.
     */
    #[Test]
    public function it_strips_markup_from_the_updated_name(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->manager)->patchJson(
            "/api/users/{$this->teamMember->id}",
            ['name' => '<script>alert(1)</script>'],
        );

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.name', 'alert(1)');
        $this->assertDatabaseHas('users', [
            'id' => $this->teamMember->id,
            'name' => 'alert(1)',
        ]);
    }

    /**
     * Allow a manager to update their own account.
     */
    #[Test]
    public function it_allows_a_manager_to_update_their_own_account(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->manager)->patchJson(
            "/api/users/{$this->manager->id}",
            ['name' => 'Renamed Manager'],
        );

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Renamed Manager');
        $this->assertDatabaseHas('users', [
            'id' => $this->manager->id,
            'name' => 'Renamed Manager',
        ]);
    }

    /**
     * Allow an admin to update a User on another Team.
     */
    #[Test]
    public function it_allows_an_admin_to_update_a_user_on_another_team(): void
    {
        // Arrange

        /** @var Team $otherTeam */
        $otherTeam = Team::factory()->create();

        /** @var User $admin */
        $admin = User::factory()->for($this->team)->admin()->create();

        /** @var User $otherUser */
        $otherUser = User::factory()->for($otherTeam)->create([
            'name' => 'Other User',
            'email' => 'other@example.com',
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson(
            "/api/users/{$otherUser->id}",
            ['name' => 'Renamed Other User'],
        );

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Renamed Other User');
        $this->assertDatabaseHas('users', [
            'id' => $otherUser->id,
            'name' => 'Renamed Other User',
            'email' => 'other@example.com',
        ]);
    }

    /**
     * Allow only an admin to reassign a User to another Team.
     */
    #[Test]
    public function it_allows_only_an_admin_to_reassign_a_user_to_another_team(): void
    {
        // Arrange

        /** @var Team $otherTeam */
        $otherTeam = Team::factory()->create();

        /** @var User $admin */
        $admin = User::factory()->for($this->team)->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson(
            "/api/users/{$this->teamMember->id}",
            ['team_id' => $otherTeam->id],
        );

        // Assert

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $this->teamMember->id,
            'team_id' => $otherTeam->id,
        ]);
    }

    /**
     * Reject Team reassignment from a manager.
     */
    #[Test]
    public function it_rejects_team_reassignment_from_a_manager(): void
    {
        // Arrange

        /** @var Team $otherTeam */
        $otherTeam = Team::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->manager)->patchJson(
            "/api/users/{$this->teamMember->id}",
            ['team_id' => $otherTeam->id],
        );

        // Assert

        $response->assertUnprocessable();
        $this->assertDatabaseHas('users', [
            'id' => $this->teamMember->id,
            'team_id' => $this->team->id,
        ]);
    }

    /**
     * Reject email and password fields.
     */
    #[Test]
    public function it_rejects_email_and_password_fields(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->manager)->patchJson(
            "/api/users/{$this->teamMember->id}",
            [
                'name' => 'Valid Name',
                'email' => 'new-email@example.com',
                'password' => 'new-password',
            ],
        );

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['email', 'password']);
    }

    /**
     * Reject an empty payload.
     */
    #[Test]
    public function it_rejects_an_empty_payload(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->manager)->patchJson(
            "/api/users/{$this->teamMember->id}",
            [],
        );

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['name']);
    }

    /*
     * Authorisation Tests
     * -------------------
     */

    /**
     * Deny updating a User on another Team.
     */
    #[Test]
    public function it_denies_updating_a_user_on_another_team(): void
    {
        // Arrange

        /** @var Team $otherTeam */
        $otherTeam = Team::factory()->create();

        /** @var User $otherUser */
        $otherUser = User::factory()->for($otherTeam)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->manager)->patchJson(
            "/api/users/{$otherUser->id}",
            ['name' => 'Should Not Apply'],
        );

        // Assert

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', [
            'id' => $otherUser->id,
            'name' => 'Should Not Apply',
        ]);
    }

    /**
     * Authorise update according to the Role matrix.
     */
    /**
     * Authorise update according to the Role matrix.
     */
    #[Test]
    /**
     * Authorise update according to the Role matrix.
     */
    #[DataProvider('roleAuthorisationProvider')]
    public function it_authorises_update_according_to_the_role_matrix(string $role, bool $canUpdate): void
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
        $response = $this->actingAs($viewer)->patchJson(
            "/api/users/{$this->teamMember->id}",
            ['name' => 'Role Matrix Update'],
        );

        // Assert

        if ($canUpdate) {
            $response->assertOk();
            $this->assertDatabaseHas('users', [
                'id' => $this->teamMember->id,
                'name' => 'Role Matrix Update',
            ]);
        } else {
            $response->assertForbidden();
            $this->assertDatabaseHas('users', [
                'id' => $this->teamMember->id,
                'name' => 'Team Member',
            ]);
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
        $response = $this->actingAs($this->manager)->patchJson(
            '/api/users/999999',
            ['name' => 'Ghost User'],
        );

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
        $response = $this->patchJson(
            "/api/users/{$this->teamMember->id}",
            ['name' => 'Anonymous Update'],
        );

        // Assert

        $response->assertUnauthorized();
    }

    /*
     * Role Assignment Tests
     * ---------------------
     */

    /**
     * Assign a new role to a User as an Admin.
     */
    #[Test]
    public function it_assigns_a_role_as_an_admin(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->for($this->team)->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson(
            "/api/users/{$this->teamMember->id}",
            ['role' => RoleName::Manager->value],
        );

        // Assert

        $response->assertOk();
        $this->assertTrue($this->teamMember->refresh()->hasRole(RoleName::Manager));
        $this->assertFalse($this->teamMember->hasRole(RoleName::User));
    }

    /**
     * Demote an Admin while another Admin remains.
     */
    #[Test]
    public function it_demotes_an_admin_while_another_remains(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->withoutTeam()->admin()->create();

        /** @var User $otherAdmin */
        $otherAdmin = User::factory()->withoutTeam()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson(
            "/api/users/{$otherAdmin->id}",
            ['role' => RoleName::Manager->value],
        );

        // Assert

        $response->assertOk();
        $this->assertTrue($otherAdmin->refresh()->hasRole(RoleName::Manager));
    }

    /**
     * Reject a role change from a Manager: `role` is prohibited without
     * `users.assign-role`, and nothing else in the payload applies either.
     */
    #[Test]
    public function it_rejects_a_role_change_from_a_manager(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($this->manager)->patchJson(
            "/api/users/{$this->teamMember->id}",
            ['name' => 'Should Not Apply', 'role' => RoleName::Admin->value],
        );

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['role']);
        $this->assertDatabaseHas('users', [
            'id' => $this->teamMember->id,
            'name' => 'Team Member',
        ]);
        $this->assertTrue($this->teamMember->refresh()->hasRole(RoleName::User));
    }

    /**
     * Reject an Admin changing their own role.
     */
    #[Test]
    public function it_rejects_an_admin_changing_their_own_role(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->for($this->team)->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson(
            "/api/users/{$admin->id}",
            ['role' => RoleName::Manager->value],
        );

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['role']);
        $this->assertTrue($admin->refresh()->hasRole(RoleName::Admin));
    }

    /**
     * Reject an unknown role value.
     */
    #[Test]
    public function it_rejects_an_unknown_role(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->for($this->team)->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson(
            "/api/users/{$this->teamMember->id}",
            ['role' => 'Superuser'],
        );

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['role']);
    }

    /**
     * Reject a role change on a service account.
     */
    #[Test]
    public function it_rejects_a_role_change_on_a_service_account(): void
    {        // Arrange

        /** @var User $admin */
        $admin = User::factory()->for($this->team)->admin()->create();

        /** @var User $serviceUser */
        $serviceUser = User::factory()->serviceAccount()->service()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson(
            "/api/users/{$serviceUser->id}",
            ['role' => RoleName::Manager->value],
        );

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['role']);
        $this->assertTrue($serviceUser->refresh()->hasRole(RoleName::Service));
    }

    /*
     * Phone Tests
     * -----------
     */

    /**
     * Update the phone number to a canonical E.164 value.
     */
    #[Test]
    public function it_updates_the_phone_number(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->for($this->team)->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson(
            "/api/users/{$this->teamMember->id}",
            ['phone' => '+353851046420'],
        );

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.phone', '+353851046420');
        $this->assertDatabaseHas('users', [
            'id' => $this->teamMember->id,
            'phone' => '+353851046420',
        ]);
    }

    /**
     * Reject a non-E.164 phone number.
     */
    #[Test]
    public function it_rejects_a_non_e164_phone_number(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->for($this->team)->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson(
            "/api/users/{$this->teamMember->id}",
            ['phone' => '0851046420'],
        );

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['phone']);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Spatie role names mapped to whether that role may update a User.
     *
     * @return array<string, array{0: string, 1: bool}> case name mapped to [role, canUpdate]
     */
    public static function roleAuthorisationProvider(): array
    {
        return [
            'Admin can update' => [RoleName::Admin->value, true],
            'Manager can update' => [RoleName::Manager->value, true],
            'User cannot update' => [RoleName::User->value, false],
        ];
    }
}
