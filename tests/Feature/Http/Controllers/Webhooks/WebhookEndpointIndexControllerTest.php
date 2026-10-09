<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Webhooks;

use App\Http\Controllers\Webhooks\WebhookEndpointIndexController;
use App\Http\Requests\Webhooks\WebhookEndpointIndexRequest;
use App\Http\Resources\WebhookEndpointResource;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Policies\WebhookEndpointPolicy;
use App\Queries\IndexFieldsQuery;
use App\Queries\IndexSortQuery;
use App\Queries\Webhooks\WebhookEndpointFilterQuery;
use App\Queries\Webhooks\WebhookEndpointQueryConstraints;
use App\Support\ApiResponse;
use App\Support\DateBoundaryParser;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the webhook endpoint index.
 */
#[CoversClass(WebhookEndpointIndexController::class)]
#[CoversClass(WebhookEndpointIndexRequest::class)]
#[CoversClass(WebhookEndpointResource::class)]
#[CoversClass(WebhookEndpointPolicy::class)]
#[CoversClass(IndexFieldsQuery::class)]
#[CoversClass(IndexSortQuery::class)]
#[CoversClass(WebhookEndpointFilterQuery::class)]
#[CoversClass(WebhookEndpointQueryConstraints::class)]
#[CoversClass(ApiResponse::class)]
#[CoversClass(DateBoundaryParser::class)]
final class WebhookEndpointIndexControllerTest extends TestCase
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
     * Index Tests
     * -----------
     */

    /**
     * Return a page of endpoints without ever exposing secrets.
     */
    #[Test]
    public function it_returns_endpoints_without_secrets(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        WebhookEndpoint::factory()->create(['name' => 'Billing Sync']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/webhook-endpoints');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Webhook Endpoints Retrieved Successfully');
        $response->assertJsonMissing(['secret' => ''], exact: false);
        $response->assertJsonMissingPath('data.0.secret');
    }

    /**
     * Filter endpoints by search term.
     */
    #[Test]
    public function it_filters_by_search_term(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        WebhookEndpoint::factory()->create(['name' => 'Billing Sync']);
        WebhookEndpoint::factory()->create(['name' => 'CRM Export']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/webhook-endpoints?filter[search]=billing');

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Billing Sync');
    }

    /**
     * Reject unknown filter keys, sorts, and fields.
     *
     * @param string $query the query string carrying the hostile param
     */
    #[Test]
    #[DataProvider('invalidQueryParamProvider')]
    public function it_rejects_invalid_query_params(string $query): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson("/api/webhook-endpoints?{$query}");

        // Assert

        $response->assertUnprocessable();
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
        $response = $this->getJson('/api/webhook-endpoints');

        // Assert

        $response->assertUnauthorized();
    }

    /*
     * Authorisation Tests
     * -------------------
     */

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
        $response = $this->actingAs($user)->getJson('/api/webhook-endpoints');

        // Assert

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Query strings carrying hostile params the index must reject.
     *
     * @return array<string, array{0: string}> case name mapped to [query]
     */
    public static function invalidQueryParamProvider(): array
    {
        return [
            'unknown filter key' => ['filter[owner]=1'],
            'unknown sort column' => ['sort=secret'],
            'unknown sparse field' => ['fields[webhook_endpoints]=id,secret'],
        ];
    }

    /*
     * Date Range Tests
     * ----------------
     */

    /**
     * Filter webhook endpoints by an inclusive lower bound on `created_at`.
     */
    #[Test]
    public function it_filters_by_from_only(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        WebhookEndpoint::factory()->create(['created_at' => '2026-10-01 10:00:00']);
        WebhookEndpoint::factory()->create(['created_at' => '2026-10-05 10:00:00']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/webhook-endpoints?filter[from]=2026-10-05');

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    /**
     * Filter webhook endpoints by an inclusive upper bound on `created_at`.
     */
    #[Test]
    public function it_filters_by_to_only(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        WebhookEndpoint::factory()->create(['created_at' => '2026-10-01 10:00:00']);
        WebhookEndpoint::factory()->create(['created_at' => '2026-10-05 10:00:00']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/webhook-endpoints?filter[to]=2026-10-01');

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    /**
     * Filter webhook endpoints by an inclusive range on `created_at`.
     */
    #[Test]
    public function it_filters_by_both_bounds(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        WebhookEndpoint::factory()->create(['created_at' => '2026-10-01 10:00:00']);
        WebhookEndpoint::factory()->create(['created_at' => '2026-10-05 10:00:00']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/webhook-endpoints?filter[from]=2026-10-01&filter[to]=2026-10-05');

        // Assert

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
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
        WebhookEndpoint::factory()->create(['created_at' => '2026-10-05 15:00:00']);
        WebhookEndpoint::factory()->create(['created_at' => '2026-10-06 00:00:01']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/webhook-endpoints?filter[to]=2026-10-05');

        // Assert

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
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
        $response = $this->actingAs($admin)->getJson('/api/webhook-endpoints?filter[from]=2026-10-05&filter[to]=2026-10-01');

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
        $response = $this->actingAs($admin)->getJson('/api/webhook-endpoints?filter[from]=not-a-date');

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
        $response = $this->actingAs($admin)->getJson('/api/webhook-endpoints?filter[from]=');

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['filter.from']);
    }
}
