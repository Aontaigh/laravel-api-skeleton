<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Permissions;

use App\DataTransferObjects\IndexSort;
use App\DataTransferObjects\Permissions\PermissionFilters;
use App\Http\Controllers\Permissions\PermissionIndexController;
use App\Http\Requests\Permissions\PermissionIndexRequest;
use App\Http\Resources\PermissionResource;
use App\Models\User;
use App\Policies\PermissionPolicy;
use App\Queries\IndexFieldsQuery;
use App\Queries\IndexSortQuery;
use App\Queries\Permissions\PermissionFilterQuery;
use App\Queries\Permissions\PermissionQueryConstraints;
use App\Support\ApiResponse;
use App\Support\CommaSeparatedList;
use App\Support\DateBoundaryParser;
use App\Support\LikePattern;
use App\Support\QualifiedColumn;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Feature tests for the Permission Index endpoint.
 */
#[CoversClass(PermissionIndexController::class)]
#[CoversClass(PermissionIndexRequest::class)]
#[CoversClass(PermissionResource::class)]
#[CoversClass(PermissionPolicy::class)]
#[CoversClass(PermissionFilterQuery::class)]
#[CoversClass(PermissionQueryConstraints::class)]
#[CoversClass(PermissionFilters::class)]
#[CoversClass(IndexFieldsQuery::class)]
#[CoversClass(IndexSortQuery::class)]
#[CoversClass(IndexSort::class)]
#[CoversClass(ApiResponse::class)]
#[CoversClass(CommaSeparatedList::class)]
#[CoversClass(LikePattern::class)]
#[CoversClass(QualifiedColumn::class)]
#[CoversClass(DateBoundaryParser::class)]
final class PermissionIndexControllerTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup / Teardown
    |--------------------------------------------------------------------------
    */

    /**
     * Seed permissions and enable strict model checks.
     *
     * @return void
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
     *
     * @return void
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
     * Return every seeded permission.
     */
    #[Test]
    public function it_returns_every_seeded_permission(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/permissions?per_page=100');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Permissions Retrieved Successfully');

        /*
         * Derived from the seeded database rather than a hardcoded count, so
         * adding a permission to the seeder cannot break this test - the index
         * must simply return every permission the seeder created.
         */
        $expected = Permission::query()->orderBy('name')->pluck('name')->all();
        $response->assertJsonPath('meta.pagination.total', count($expected));
        $response->assertJsonCount(count($expected), 'data');
    }

    /**
     * Filter permissions by the search term.
     */
    #[Test]
    public function it_filters_by_search_term(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/permissions?filter[search]=tokens');

        // Assert

        $response->assertOk();
        $response->assertJsonCount(4, 'data');
        $response->assertJsonPath('data.0.name', 'tokens.list-own');
        $response->assertJsonPath('data.1.name', 'tokens.create-own');
        $response->assertJsonPath('data.2.name', 'tokens.revoke-own');
        $response->assertJsonPath('data.3.name', 'tokens.create-for-user');
    }

    /**
     * Allow regular users with permissions list access.
     */
    #[Test]
    public function it_allows_regular_users_with_permissions_list_access(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->getJson('/api/permissions');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('meta.pagination.total', Permission::query()->count());
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
        $response = $this->getJson('/api/permissions');

        // Assert

        $response->assertUnauthorized();
    }

    /*
     * Authorisation Tests
     * -------------------
     */

    /**
     * Deny service accounts without permissions list access.
     */
    #[Test]
    public function it_denies_service_accounts_without_permissions_list_access(): void
    {
        // Arrange

        /** @var User $service */
        $service = User::factory()->service()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($service)->getJson('/api/permissions');

        // Assert

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
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/permissions?'.$queryString);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, [$expectedErrorKey]);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Invalid query strings and the validation key they should hit.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidQueryProvider(): array
    {
        return [
            'unsupported include' => ['include=user', 'include'],
            'unsupported sort' => ['sort=created_at', 'sort'],
            'unsupported filter' => ['filter[event]=login', 'filter.event'],
            'unsupported fields key' => ['fields[users]=id', 'fields.users'],
            'unsupported fields column' => ['fields[permissions]=guard_name', 'fields.permissions'],
        ];
    }

    /*
     * Date Range Tests
     * ----------------
     */

    /**
     * Filter permissions by an inclusive lower bound on `created_at`.
     */
    #[Test]
    public function it_filters_by_from_only(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        Permission::query()->update(['created_at' => '2026-10-01 10:00:00']);
        Permission::whereIn('id', Permission::query()->limit(2)->pluck('id'))
            ->update(['created_at' => '2026-10-05 10:00:00']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/permissions?filter[from]=2026-10-05&per_page=100');

        // Assert

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    /**
     * Filter permissions by an inclusive upper bound on `created_at`.
     */
    #[Test]
    public function it_filters_by_to_only(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        Permission::query()->update(['created_at' => '2026-10-01 10:00:00']);
        Permission::whereIn('id', Permission::query()->limit(2)->pluck('id'))
            ->update(['created_at' => '2026-10-05 10:00:00']);

        $total = Permission::query()->count();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/permissions?filter[to]=2026-10-01&per_page=100');

        // Assert

        $response->assertOk();
        $response->assertJsonCount($total - 2, 'data');
    }

    /**
     * Filter permissions by an inclusive range on `created_at`.
     */
    #[Test]
    public function it_filters_by_both_bounds(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        Permission::query()->update(['created_at' => '2026-10-03 10:00:00']);

        $total = Permission::query()->count();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/permissions?filter[from]=2026-10-01&filter[to]=2026-10-05&per_page=100');

        // Assert

        $response->assertOk();
        $response->assertJsonCount($total, 'data');
    }

    /**
     * Include the whole named day for a bare date upper bound.
     */
    #[Test]
    public function it_includes_the_whole_day_for_a_bare_date_to_bound(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        Permission::query()->update(['created_at' => '2026-10-05 15:00:00']);
        Permission::query()->limit(1)->update(['created_at' => '2026-10-06 00:00:01']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/permissions?filter[to]=2026-10-05&per_page=100');

        // Assert

        $response->assertOk();

        $total = Permission::query()->count();
        $response->assertJsonCount($total - 1, 'data');
    }

    /**
     * Reject a range whose upper bound precedes its lower bound.
     */
    #[Test]
    public function it_rejects_a_to_bound_before_the_from_bound(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/permissions?filter[from]=2026-10-05&filter[to]=2026-10-01');

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['filter.to']);
    }

    /**
     * Reject a bound Carbon cannot parse.
     */
    #[Test]
    public function it_rejects_an_unparseable_from_date(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/permissions?filter[from]=not-a-date');

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['filter.from']);
    }

    /**
     * Reject a blank bound instead of narrowing to nothing.
     *
     * A blank date is malformed input, not an empty list: unlike a comma list, there is no
     * "empty date" that could mean absent, so the `date` rule refuses it.
     */
    #[Test]
    public function it_rejects_a_blank_from_bound(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/permissions?filter[from]=');

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['filter.from']);
    }
}
