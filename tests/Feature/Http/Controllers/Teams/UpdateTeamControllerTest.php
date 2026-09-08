<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Teams;

use App\Actions\Teams\UpdateTeamAction;
use App\DataTransferObjects\Teams\UpdateTeamData;
use App\Http\Controllers\Teams\UpdateTeamController;
use App\Http\Requests\Teams\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
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
use Tests\Concerns\AssertsApiEnvelope;
use Tests\TestCase;

/**
 * Feature tests for Team update.
 */
#[CoversClass(UpdateTeamController::class)]
#[CoversClass(UpdateTeamRequest::class)]
#[CoversClass(UpdateTeamAction::class)]
#[CoversClass(UpdateTeamData::class)]
#[CoversClass(TeamResource::class)]
#[CoversClass(TeamPolicy::class)]
#[CoversClass(ApiResponse::class)]
final class UpdateTeamControllerTest extends TestCase
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
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Seed roles and permissions.
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
     * Update a Team name.
     */
    #[Test]
    public function it_updates_a_team_name(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['name' => 'Engineering']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson("/api/teams/{$team->id}", [
            'name' => 'Platform',
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Team Updated Successfully');
        $response->assertJsonPath('data.name', 'Platform');

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'Platform']);
    }

    /**
     * Strip markup from the Team name before validation.
     */
    #[Test]
    public function it_strips_markup_from_the_team_name(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['name' => 'Engineering']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson("/api/teams/{$team->id}", [
            'name' => '<b>Platform</b>',
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Platform');
    }

    /**
     * Reject a name taken by another Team.
     */
    #[Test]
    public function it_rejects_a_name_taken_by_another_team(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['name' => 'Engineering']);
        /** @var Team $other */
        $other = Team::factory()->create(['name' => 'Platform']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson("/api/teams/{$team->id}", [
            'name' => $other->name,
        ]);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['name']);
    }

    /**
     * Accept the Team's own unchanged name.
     */
    #[Test]
    public function it_accepts_the_unchanged_name(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['name' => 'Engineering']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson("/api/teams/{$team->id}", [
            'name' => 'Engineering',
        ]);

        // Assert

        $response->assertOk();
    }

    /**
     * Reject an empty update body.
     */
    #[Test]
    public function it_rejects_an_empty_update_body(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['name' => 'Engineering']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson("/api/teams/{$team->id}", []);

        // Assert

        $response->assertUnprocessable();
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
        $response = $this->actingAs($admin)->patchJson('/api/teams/999999', [
            'name' => 'Platform',
        ]);

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
        $response = $this->patchJson("/api/teams/{$team->id}", ['name' => 'Platform']);

        // Assert

        $response->assertUnauthorized();
    }

    /*
     * Authorization Tests
     * -------------------
     */

    /**
     * Deny Managers: they hold `teams.list` but not `teams.update`.
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
        $response = $this->actingAs($manager)->patchJson("/api/teams/{$team->id}", [
            'name' => 'Platform',
        ]);

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
        $response = $this->actingAs($user)->patchJson("/api/teams/{$team->id}", [
            'name' => 'Platform',
        ]);

        // Assert

        $response->assertForbidden();
    }
}
