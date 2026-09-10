<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Webhooks;

use App\Actions\Webhooks\DeleteWebhookEndpointAction;
use App\Http\Controllers\Webhooks\DestroyWebhookEndpointController;
use App\Http\Requests\Webhooks\DestroyWebhookEndpointRequest;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Policies\WebhookEndpointPolicy;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for webhook endpoint deletion.
 */
#[CoversClass(DestroyWebhookEndpointController::class)]
#[CoversClass(DestroyWebhookEndpointRequest::class)]
#[CoversClass(DeleteWebhookEndpointAction::class)]
#[CoversClass(WebhookEndpointPolicy::class)]
#[CoversClass(ApiResponse::class)]
final class DestroyWebhookEndpointControllerTest extends TestCase
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
     * Mutation Tests
     * --------------
     */

    /**
     * Delete an endpoint together with its delivery history.
     */
    #[Test]
    public function it_deletes_an_endpoint_with_its_delivery_history(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        WebhookDelivery::factory()->for($endpoint, 'endpoint')->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->deleteJson("/api/webhook-endpoints/{$endpoint->id}");

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Webhook Endpoint Deleted Successfully');

        $this->assertDatabaseMissing('webhook_endpoints', ['id' => $endpoint->id]);
        $this->assertDatabaseMissing('webhook_deliveries', ['webhook_endpoint_id' => $endpoint->id]);
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
        $response = $this->actingAs($admin)->deleteJson('/api/webhook-endpoints/999999');

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
        $response = $this->deleteJson("/api/webhook-endpoints/{$endpoint->id}");

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

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->deleteJson("/api/webhook-endpoints/{$endpoint->id}");

        // Assert

        $response->assertForbidden();
    }
}
