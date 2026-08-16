<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Teams;

use App\DataTransferObjects\IndexSort;
use App\DataTransferObjects\Teams\TeamFilters;
use App\Http\Controllers\Teams\TeamIndexController;
use App\Http\Requests\Teams\TeamIndexRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Models\User;
use App\Policies\TeamPolicy;
use App\Queries\IndexFieldsQuery;
use App\Queries\IndexSortQuery;
use App\Queries\Teams\TeamFilterQuery;
use App\Queries\Teams\TeamQueryConstraints;
use App\Support\ApiResponse;
use App\Support\LikePattern;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the Team index endpoint.
 */
#[CoversClass(TeamIndexController::class)]
#[CoversClass(TeamIndexRequest::class)]
#[CoversClass(TeamResource::class)]
#[CoversClass(TeamPolicy::class)]
#[CoversClass(TeamFilterQuery::class)]
#[CoversClass(LikePattern::class)]
#[CoversClass(TeamQueryConstraints::class)]
#[CoversClass(IndexFieldsQuery::class)]
#[CoversClass(IndexSortQuery::class)]
#[CoversClass(IndexSort::class)]
#[CoversClass(TeamFilters::class)]
#[CoversClass(ApiResponse::class)]
final class TeamIndexControllerTest extends TestCase
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
     * Seed permissions and enable strict Eloquent modes.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading();
        Model::preventAccessingMissingAttributes();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Restore the global strict-mode flags so they do not leak into other suites.
     */
    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        Model::preventAccessingMissingAttributes(false);

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /*
     * Listing Tests
     * -------------
     */

    /**
     * Return every Team row for an admin caller.
     *
     * Asserts presence of the created teams rather than an exact count so
     * the test does not break when concurrent agents share the database.
     */
    #[Test]
    public function it_lists_teams_for_admins(): void
    {
        /* Arrange */

        /** @var User $admin */
        $admin = User::factory()->withoutTeam()->admin()->create();
        $teams = Team::factory()->count(3)->create();
        /** @var list<int> $expectedIds */
        $expectedIds = $teams->pluck('id')->all();

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/teams');

        /* Assert */

        $response->assertOk();
        $response->assertJsonPath('message', 'Teams Retrieved Successfully');

        /** @var list<array{id: int, name: string}> $data */
        $data = $response->json('data');

        $actualIds = array_map(
            static fn (array $row): int => $row['id'],
            $data,
        );

        foreach ($expectedIds as $id) {
            $this->assertContains($id, $actualIds, 'Team '.(string) $id.' is missing from the response.');
        }
    }

    /**
     * Filter teams by partial name search.
     *
     * Uses a unique name so the filter never collides with unrelated teams
     * from concurrent agents.
     */
    #[Test]
    public function it_filters_teams_by_search_term(): void
    {
        /* Arrange */

        /** @var User $admin */
        $admin = User::factory()->withoutTeam()->admin()->create();
        $target = Team::factory()->create(['name' => 'Zzz-Unique-Filter-Target']);
        Team::factory()->create(['name' => 'Zzz-Other-Team']);

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            '/api/teams?filter[search]=Unique-Filter',
        );

        /* Assert */

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $target->id);
        $response->assertJsonPath('data.0.name', 'Zzz-Unique-Filter-Target');
    }

    /**
     * Treat LIKE wildcards in filter[search] as literal characters.
     */
    #[Test]
    public function it_treats_like_wildcards_in_search_as_literal_characters(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->withoutTeam()->admin()->create();
        $target = Team::factory()->create(['name' => 'Zzz-Literal-Percent%Team']);
        Team::factory()->create(['name' => 'Zzz-No-Percent-Team']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            '/api/teams?filter[search]='.rawurlencode('%'),
        );

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $target->id);
        $response->assertJsonPath('data.0.name', 'Zzz-Literal-Percent%Team');
    }

    /**
     * Sort teams by name ascending.
     *
     * Uses names prefixed with "Aaa-" so they always appear before
     * any random-name team from concurrent agents.
     */
    #[Test]
    public function it_sorts_teams_by_name_ascending(): void
    {
        /* Arrange */

        /** @var User $admin */
        $admin = User::factory()->withoutTeam()->admin()->create();
        $second = Team::factory()->create(['name' => 'Aaa-Second']);
        $first = Team::factory()->create(['name' => 'Aaa-First']);

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/teams?sort=name');

        /* Assert */

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $first->id);
        $response->assertJsonPath('data.0.name', 'Aaa-First');
        $response->assertJsonPath('data.1.id', $second->id);
        $response->assertJsonPath('data.1.name', 'Aaa-Second');
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
        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/teams');

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

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->getJson('/api/teams');

        /* Assert */

        $response->assertForbidden();
    }

    /*
     * Validation Tests
     * ----------------
     */

    /**
     * Reject hostile and out-of-allow-list query params.
     */
    #[Test]
    #[DataProvider('invalidQueryProvider')]
    public function it_rejects_invalid_query_params(string $queryString, string $expectedErrorKey): void
    {
        /* Arrange */

        /** @var User $admin */
        $admin = User::factory()->withoutTeam()->admin()->create();

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson("/api/teams?{$queryString}");

        /* Assert */

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, [$expectedErrorKey]);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Hostile and out-of-allow-list query params mapped to the key that must error.
     *
     * @return array<string, array{0: string, 1: string}> case name mapped to [queryString, expectedErrorKey]
     */
    public static function invalidQueryProvider(): array
    {
        return [
            'unknown sort column' => ['sort=secret', 'sort'],
            'unknown filter key' => ['filter[is_active]=1', 'filter.is_active'],
            'unknown sparse field' => ['fields[teams]=id,secret', 'fields.teams'],
            'unknown fields resource' => ['fields[roles]=id', 'fields.roles'],
        ];
    }
}
