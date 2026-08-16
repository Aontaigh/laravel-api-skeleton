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
        $response = $this->actingAs($admin)->getJson('/api/permissions');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Permissions Retrieved Successfully');
        $response->assertJsonPath('meta.pagination.total', 17);
        $response->assertJsonCount(17, 'data');
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
        $response->assertJsonPath('meta.pagination.total', 17);
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
     * Authorization Tests
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
}
