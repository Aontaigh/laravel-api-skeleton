<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Clients;

use App\DataTransferObjects\ApiClients\ApiClientFilters;
use App\DataTransferObjects\IndexSort;
use App\Http\Controllers\Clients\ClientIndexController;
use App\Http\Requests\ApiClients\ClientIndexRequest;
use App\Http\Resources\ApiClientResource;
use App\Models\ApiClient;
use App\Models\User;
use App\Policies\ApiClientPolicy;
use App\Queries\ApiClients\ApiClientFilterQuery;
use App\Queries\ApiClients\ApiClientQueryConstraints;
use App\Queries\IndexFieldsQuery;
use App\Queries\IndexSortQuery;
use App\Support\ApiDateTime;
use App\Support\ApiResponse;
use App\Support\CommaSeparatedList;
use App\Support\LikePattern;
use App\Support\QualifiedColumn;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the API client index.
 */
#[CoversClass(ClientIndexController::class)]
#[CoversClass(ClientIndexRequest::class)]
#[CoversClass(ApiClientResource::class)]
#[CoversClass(ApiClientPolicy::class)]
#[CoversClass(ApiClientFilterQuery::class)]
#[CoversClass(ApiClientQueryConstraints::class)]
#[CoversClass(ApiClientFilters::class)]
#[CoversClass(IndexFieldsQuery::class)]
#[CoversClass(IndexSortQuery::class)]
#[CoversClass(IndexSort::class)]
#[CoversClass(ApiResponse::class)]
#[CoversClass(ApiDateTime::class)]
#[CoversClass(CommaSeparatedList::class)]
#[CoversClass(LikePattern::class)]
#[CoversClass(QualifiedColumn::class)]
final class ClientIndexControllerTest extends TestCase
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
     * Seed permissions for API client management.
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
     * Listing Tests
     * -------------
     */

    /**
     * Return every API client for an admin caller.
     */
    #[Test]
    public function it_lists_api_clients_for_admins(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        ApiClient::factory()->count(2)->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/clients');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'API Clients Retrieved Successfully');
        $response->assertJsonCount(2, 'data');
    }

    /**
     * Filter clients by the search term.
     */
    #[Test]
    public function it_filters_clients_by_search_term(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        ApiClient::factory()->create(['name' => 'Billing Sync']);
        ApiClient::factory()->create(['name' => 'Other Client']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/clients?filter[search]=Billing');

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Billing Sync');
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
        $response = $this->getJson('/api/clients');

        // Assert

        $response->assertUnauthorized();
    }

    /*
     * Authorization Tests
     * -------------------
     */

    /**
     * Deny managers without API client list permission.
     */
    #[Test]
    public function it_denies_managers_without_api_client_list_permission(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($manager)->getJson('/api/clients');

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
        $response = $this->actingAs($admin)->getJson("/api/clients?{$queryString}");

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
     * Hostile and out-of-allow-list query params mapped to the key that must error.
     *
     * @return array<string, array{0: string, 1: string}> case name mapped to [queryString, expectedErrorKey]
     */
    public static function invalidQueryProvider(): array
    {
        return [
            'unknown sort column' => ['sort=token', 'sort'],
            'unsupported include' => ['include=user', 'include'],
            'unknown filter key' => ['filter[is_active]=1', 'filter.is_active'],
            'unknown sparse field' => ['fields[api_clients]=id,secret', 'fields.api_clients'],
            'unknown fields resource' => ['fields[users]=id', 'fields.users'],
            'array-shaped sort' => ['sort[]=name', 'sort'],
            'array-shaped sparse field' => ['fields[api_clients][]=id', 'fields.api_clients'],
            'scalar filter container' => ['filter=name', 'filter'],
            'scalar fields container' => ['fields=name', 'fields'],
            'page size above the hard maximum' => [
                'per_page='.(ApiClientQueryConstraints::MAX_PER_PAGE + 1),
                'per_page',
            ],
        ];
    }
}
