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
     * Authorization Tests
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
}
