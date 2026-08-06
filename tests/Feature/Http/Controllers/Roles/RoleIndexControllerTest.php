<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Roles;

use App\DataTransferObjects\ListSort;
use App\DataTransferObjects\Roles\RoleFilters;
use App\Enums\RoleName;
use App\Http\Controllers\Roles\RoleIndexController;
use App\Http\Requests\Roles\RoleIndexRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Queries\ListFieldsQuery;
use App\Queries\ListSortQuery;
use App\Queries\Permissions\PermissionQueryConstraints;
use App\Queries\Roles\RoleFilterQuery;
use App\Queries\Roles\RoleIncludeQuery;
use App\Queries\Roles\RoleQueryConstraints;
use App\Support\ApiDateTime;
use App\Support\ApiResponse;
use App\Support\CommaSeparatedList;
use App\Support\LikePattern;
use App\Support\QualifiedColumn;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature tests for the Role Index Endpoint.
 */
#[CoversClass(RoleIndexController::class)]
#[CoversClass(RoleIndexRequest::class)]
#[CoversClass(RoleResource::class)]
#[CoversClass(PermissionResource::class)]
#[CoversClass(RolePolicy::class)]
#[CoversClass(RoleFilterQuery::class)]
#[CoversClass(RoleIncludeQuery::class)]
#[CoversClass(RoleQueryConstraints::class)]
#[CoversClass(PermissionQueryConstraints::class)]
#[CoversClass(RoleFilters::class)]
#[CoversClass(ListFieldsQuery::class)]
#[CoversClass(ListSortQuery::class)]
#[CoversClass(ListSort::class)]
#[CoversClass(ApiResponse::class)]
#[CoversClass(ApiDateTime::class)]
#[CoversClass(CommaSeparatedList::class)]
#[CoversClass(LikePattern::class)]
#[CoversClass(QualifiedColumn::class)]
final class RoleIndexControllerTest extends TestCase
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
     * Seed permissions and enable strict model checks.
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
     * Listing Tests
     * -------------
     */

    /**
     * Return every seeded Role.
     */
    #[Test]
    public function it_returns_every_seeded_role(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/roles');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.*.name', [
            RoleName::Admin->value,
            RoleName::Manager->value,
            RoleName::User->value,
        ]);
        $response->assertJsonPath(
            'data.*.id',
            Role::query()->orderBy('id')->pluck('id')->all(),
        );
        $response->assertJsonPath('meta.pagination.total', 3);
    }

    /**
     * Filter Roles by the search term.
     */
    #[Test]
    public function it_filters_by_search_term(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/roles?filter[search]=Admin');

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', RoleName::Admin->value);
    }

    /*
     * Pagination Tests
     * ----------------
     */

    /**
     * Return a later page with accurate pagination meta.
     */
    #[Test]
    public function it_returns_a_later_page_with_accurate_pagination_meta(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/roles?per_page=2&page=2');

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', RoleName::User->value);
        $response->assertJsonPath('meta.pagination.current_page', 2);
        $response->assertJsonPath('meta.pagination.per_page', 2);
        $response->assertJsonPath('meta.pagination.total', 3);
        $response->assertJsonPath('meta.pagination.last_page', 2);
    }

    /*
     * Sorting Tests
     * -------------
     */

    /**
     * Apply the documented default sort when sort is omitted.
     */
    #[Test]
    public function it_applies_the_documented_default_sort_when_sort_is_omitted(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/roles');

        // Assert

        $response->assertOk();
        $response->assertJsonPath(
            'data.*.id',
            Role::query()->orderBy('id')->pluck('id')->all(),
        );
    }

    /**
     * Sort descending when the column is prefixed with a hyphen.
     */
    #[Test]
    public function it_sorts_descending_when_the_column_is_prefixed_with_a_hyphen(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/roles?sort=-name');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.*.name', [
            RoleName::User->value,
            RoleName::Manager->value,
            RoleName::Admin->value,
        ]);
    }

    /*
     * Include and Fields Tests
     * ------------------------
     */

    /**
     * Eager-load every allow-listed include.
     */
    #[Test]
    /**
     * Eager-load every allow-listed include.
     */
    #[DataProvider('allowedIncludeProvider')]
    public function it_eager_loads_every_allow_listed_include(string $include): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson("/api/roles?include={$include}");

        // Assert

        $response->assertOk();
        $this->assertNotEmpty($response->json("data.0.{$include}"));
    }

    /**
     * Return only requested Role columns in sparse fieldsets.
     */
    #[Test]
    public function it_returns_only_requested_role_columns_in_sparse_fieldsets(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/roles?fields[roles]=id,name');

        // Assert

        $response->assertOk();

        /** @var array<string, mixed> $role */
        $role = $response->json('data.0');

        $this->assertSame(['id', 'name'], array_keys($role));
    }

    /**
     * Constrain nested Permission columns when Permissions are included.
     */
    #[Test]
    public function it_constrains_nested_permission_columns_when_permissions_are_included(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            '/api/roles?include=permissions&fields[permissions]=id,name&filter[search]=Admin',
        );

        // Assert

        $response->assertOk();

        /** @var list<array<string, mixed>> $permissions */
        $permissions = $response->json('data.0.permissions');

        $this->assertNotEmpty($permissions);

        /** @var array<string, mixed> $permissionPayload */
        $permissionPayload = $permissions[0];

        $this->assertSame(['id', 'name'], array_keys($permissionPayload));

        $permissionNames = array_column($permissions, 'name');

        $this->assertContains('roles.list', $permissionNames);
        $this->assertContains('users.list-all', $permissionNames);
    }

    /*
     * Validation Tests
     * ----------------
     */

    /**
     * Reject out-of-allow-list query params.
     */
    #[Test]
    /**
     * Reject out-of-allow-list query params.
     */
    #[DataProvider('invalidQueryProvider')]
    public function it_rejects_out_of_allow_list_query_params(string $queryString, string $expectedErrorKey): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson("/api/roles?{$queryString}");

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, [$expectedErrorKey]);
    }

    /*
     * Authorisation Tests
     * -------------------
     */

    /**
     * Authorise the index according to the Role matrix.
     */
    #[Test]
    /**
     * Authorise the index according to the Role matrix.
     */
    #[DataProvider('roleAuthorisationProvider')]
    public function it_authorises_the_index_according_to_the_role_matrix(string $role, bool $canList): void
    {
        // Arrange

        $factory = match ($role) {
            RoleName::Admin->value => User::factory()->admin(),
            RoleName::Manager->value => User::factory()->manager(),
            RoleName::User->value => User::factory()->user(),
            default => throw new InvalidArgumentException("Unmapped Role: {$role}"),
        };

        /** @var User $viewer */
        $viewer = $factory->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($viewer)->getJson('/api/roles');

        // Assert

        if ($canList) {
            $response->assertOk();
        } else {
            $response->assertForbidden();
        }
    }

    /**
     * Deny unauthenticated requests.
     */
    #[Test]
    public function it_denies_unauthenticated_requests(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/roles');

        // Assert

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Every relation key the Role Index advertises via `?include=`.
     *
     * @return array<string, array{0: string}> include key mapped to [include]
     */
    public static function allowedIncludeProvider(): array
    {
        $cases = [];

        foreach (RoleQueryConstraints::ALLOWED_INCLUDES as $include) {
            $cases[$include] = [$include];
        }

        return $cases;
    }

    /**
     * Spatie role names mapped to whether that role may list Roles.
     *
     * @return array<string, array{0: string, 1: bool}> case name mapped to [role, canList]
     */
    public static function roleAuthorisationProvider(): array
    {
        return [
            'Admin can list' => [RoleName::Admin->value, true],
            'Manager can list' => [RoleName::Manager->value, true],
            'User cannot list' => [RoleName::User->value, false],
        ];
    }

    /**
     * Hostile and out-of-allow-list Query Params mapped to the key that must error.
     *
     * @return array<string, array{0: string, 1: string}> case name mapped to [queryString, expectedErrorKey]
     */
    public static function invalidQueryProvider(): array
    {
        return [
            'unknown sort column' => ['sort=guard_name', 'sort'],
            'unknown filter key' => ['filter[team_id]=1', 'filter.team_id'],
            'unknown sparse field' => ['fields[roles]=id,guard_name', 'fields.roles'],
            'unknown fields resource' => ['fields[users]=id', 'fields.users'],
            'unknown include' => ['include=users', 'include'],
            'array-shaped sort' => ['sort[]=name', 'sort'],
            'array-shaped include' => ['include[]=permissions', 'include'],
            'array-shaped sparse field' => ['fields[roles][]=id', 'fields.roles'],
            'scalar filter container' => ['filter=name', 'filter'],
            'scalar fields container' => ['fields=name', 'fields'],
            'page size above the hard maximum' => [
                'per_page='.(RoleQueryConstraints::MAX_PER_PAGE + 1),
                'per_page',
            ],
            'page size below one' => ['per_page=0', 'per_page'],
            'page below one' => ['page=0', 'page'],
        ];
    }
}
