<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Teams;

use App\Actions\Teams\CreateTeamAction;
use App\DataTransferObjects\Teams\CreateTeamData;
use App\Http\Controllers\Teams\StoreTeamController;
use App\Http\Requests\Teams\StoreTeamRequest;
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
 * Feature tests for Team creation.
 */
#[CoversClass(StoreTeamController::class)]
#[CoversClass(StoreTeamRequest::class)]
#[CoversClass(CreateTeamAction::class)]
#[CoversClass(CreateTeamData::class)]
#[CoversClass(TeamResource::class)]
#[CoversClass(TeamPolicy::class)]
#[CoversClass(ApiResponse::class)]
final class StoreTeamControllerTest extends TestCase
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
     * Create a Team.
     */
    #[Test]
    public function it_creates_a_team(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/teams', [
            'name' => 'Engineering',
        ]);

        // Assert

        $response->assertCreated();
        $response->assertJsonPath('message', 'Team Created Successfully');
        $response->assertJsonPath('data.name', 'Engineering');

        $this->assertDatabaseHas('teams', ['name' => 'Engineering']);
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

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/teams', [
            'name' => '<script>alert(1)</script>Engineering',
        ]);

        // Assert

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'alert(1)Engineering');
    }

    /**
     * Reject a duplicate Team name.
     */
    #[Test]
    public function it_rejects_a_duplicate_team_name(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['name' => 'Engineering']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/teams', [
            'name' => $team->name,
        ]);

        // Assert

        $response->assertUnprocessable();
    }

    /**
     * Require a Team name.
     */
    #[Test]
    public function it_requires_a_team_name(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/teams', []);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['name']);
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
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/teams', ['name' => 'Engineering']);

        // Assert

        $response->assertUnauthorized();
    }

    /*
     * Authorization Tests
     * -------------------
     */

    /**
     * Deny Managers: they hold `teams.list` but not `teams.create`.
     */
    #[Test]
    public function it_denies_managers(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($manager)->postJson('/api/teams', [
            'name' => 'Engineering',
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

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->postJson('/api/teams', [
            'name' => 'Engineering',
        ]);

        // Assert

        $response->assertForbidden();
    }
}
