<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Webhooks;

use App\Http\Controllers\Webhooks\WebhookEndpointShowController;
use App\Http\Requests\Webhooks\WebhookEndpointShowRequest;
use App\Http\Resources\WebhookEndpointResource;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Policies\WebhookEndpointPolicy;
use App\Queries\IndexFieldsQuery;
use App\Queries\Webhooks\WebhookEndpointQueryConstraints;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the webhook endpoint show endpoint.
 */
#[CoversClass(WebhookEndpointShowController::class)]
#[CoversClass(WebhookEndpointShowRequest::class)]
#[CoversClass(WebhookEndpointResource::class)]
#[CoversClass(WebhookEndpointPolicy::class)]
#[CoversClass(IndexFieldsQuery::class)]
#[CoversClass(WebhookEndpointQueryConstraints::class)]
#[CoversClass(ApiResponse::class)]
final class WebhookEndpointShowControllerTest extends TestCase
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
     * Show Tests
     * ----------
     */

    /**
     * Return an endpoint by id without its secret.
     */
    #[Test]
    public function it_returns_an_endpoint_without_its_secret(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create(['name' => 'Billing Sync']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson("/api/webhook-endpoints/{$endpoint->id}");

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Webhook Endpoint Retrieved Successfully');
        $response->assertJsonPath('data.name', 'Billing Sync');
        $response->assertJsonMissingPath('data.secret');
    }

    /**
     * Support sparse fieldsets.
     */
    #[Test]
    public function it_supports_sparse_fieldsets(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            "/api/webhook-endpoints/{$endpoint->id}?fields[webhook_endpoints]=id,name",
        );

        // Assert

        $response->assertOk();
        $response->assertJsonMissingPath('data.url');
    }

    /**
     * Return not found for a nonexistent endpoint.
     */
    #[Test]
    public function it_returns_not_found_for_a_nonexistent_endpoint(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/webhook-endpoints/999999');

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

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson("/api/webhook-endpoints/{$endpoint->id}");

        // Assert

        $response->assertUnauthorized();
    }

    /*
     * Authorization Tests
     * -------------------
     */

    /**
     * Deny Managers.
     */
    #[Test]
    public function it_denies_managers(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($manager)->getJson("/api/webhook-endpoints/{$endpoint->id}");

        // Assert

        $response->assertForbidden();
    }
}
