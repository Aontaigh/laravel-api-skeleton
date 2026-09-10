<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Teams;

use App\Actions\Teams\DeleteTeamAction;
use App\Http\Controllers\Teams\DestroyTeamController;
use App\Http\Requests\Teams\DestroyTeamRequest;
use App\Models\Team;
use App\Models\User;
use App\Policies\TeamPolicy;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for Team deletion.
 */
#[CoversClass(DestroyTeamController::class)]
#[CoversClass(DestroyTeamRequest::class)]
#[CoversClass(DeleteTeamAction::class)]
#[CoversClass(TeamPolicy::class)]
#[CoversClass(ApiResponse::class)]
final class DestroyTeamControllerTest extends TestCase
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
     * Seed roles and permissions.
     *
     * @return void
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
     * Delete a Team with no assigned Users.
     */
    #[Test]
    public function it_deletes_a_team_with_no_assigned_users(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['name' => 'Engineering']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->deleteJson("/api/teams/{$team->id}");

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Team Deleted Successfully');

        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }

    /**
     * Refuse to delete a Team that still has assigned Users.
     */
    #[Test]
    public function it_refuses_to_delete_a_team_with_assigned_users(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['name' => 'Engineering']);
        User::factory()->create(['team_id' => $team->id]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->deleteJson("/api/teams/{$team->id}");

        // Assert

        $response->assertUnprocessable();

        $this->assertDatabaseHas('teams', ['id' => $team->id]);
    }

    /**
     * Return not found for a nonexistent Team.
     */
    #[Test]
    public function it_returns_not_found_for_a_nonexistent_team(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->deleteJson('/api/teams/999999');

        // Assert

        $response->assertNotFound();
    }

    /*
     * Authentication Tests
     * --------------------
     */

    /**
     * Deny unauthenticated requests.
     */
    #[Test]
    public function it_denies_unauthenticated_requests(): void
    {
        // Arrange

        /** @var Team $team */
        $team = Team::factory()->create(['name' => 'Engineering']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->deleteJson("/api/teams/{$team->id}");

        // Assert

        $response->assertUnauthorized();
    }

    /*
     * Authorization Tests
     * -------------------
     */

    /**
     * Deny Managers: they hold `teams.list` but not `teams.delete`.
     */
    #[Test]
    public function it_denies_managers(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['name' => 'Engineering']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($manager)->deleteJson("/api/teams/{$team->id}");

        // Assert

        $response->assertForbidden();
    }

    /**
     * Deny regular Users.
     */
    #[Test]
    public function it_denies_regular_users(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['name' => 'Engineering']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->deleteJson("/api/teams/{$team->id}");

        // Assert

        $response->assertForbidden();
    }
}
