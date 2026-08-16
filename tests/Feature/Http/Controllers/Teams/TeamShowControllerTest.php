<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Teams;

use App\Http\Controllers\Teams\TeamShowController;
use App\Http\Requests\Teams\TeamShowRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Models\User;
use App\Policies\TeamPolicy;
use App\Queries\IndexFieldsQuery;
use App\Queries\Teams\TeamQueryConstraints;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the Team show endpoint.
 */
#[CoversClass(TeamShowController::class)]
#[CoversClass(TeamShowRequest::class)]
#[CoversClass(TeamResource::class)]
#[CoversClass(TeamPolicy::class)]
#[CoversClass(IndexFieldsQuery::class)]
#[CoversClass(TeamQueryConstraints::class)]
#[CoversClass(ApiResponse::class)]
final class TeamShowControllerTest extends TestCase
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
     * Show Tests
     * ----------
     */

    /**
     * Return a Team by id.
     */
    #[Test]
    public function it_returns_a_team_by_id(): void
    {
        /* Arrange */

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['name' => 'Engineering']);

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson("/api/teams/{$team->id}");

        /* Assert */

        $response->assertOk();
        $response->assertJsonPath('message', 'Team Retrieved Successfully');
        $response->assertJsonPath('data.id', $team->id);
        $response->assertJsonPath('data.name', 'Engineering');
    }

    /**
     * Return not found for a nonexistent Team.
     */
    #[Test]
    public function it_returns_not_found_for_a_nonexistent_team(): void
    {
        /* Arrange */

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/teams/999999');

        /* Assert */

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
        /* Arrange */

        /** @var Team $team */
        $team = Team::factory()->create();

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson("/api/teams/{$team->id}");

        /* Assert */

        $response->assertUnauthorized();
    }

    /*
     * Authorization Tests
     * -------------------
     */

    /**
     * Deny regular users without the `teams.list` permission.
     */
    #[Test]
    public function it_denies_regular_users(): void
    {
        /* Arrange */

        /** @var User $user */
        $user = User::factory()->user()->create();
        /** @var Team $team */
        $team = Team::factory()->create();

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->getJson("/api/teams/{$team->id}");

        /* Assert */

        $response->assertForbidden();
    }
}
