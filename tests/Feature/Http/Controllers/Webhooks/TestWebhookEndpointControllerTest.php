<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Webhooks;

use App\Actions\Webhooks\PingWebhookEndpointAction;
use App\Http\Controllers\Webhooks\TestWebhookEndpointController;
use App\Http\Requests\Webhooks\TestWebhookEndpointRequest;
use App\Http\Resources\WebhookDeliveryResource;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Policies\WebhookEndpointPolicy;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for webhook test pings.
 */
#[CoversClass(TestWebhookEndpointController::class)]
#[CoversClass(TestWebhookEndpointRequest::class)]
#[CoversClass(PingWebhookEndpointAction::class)]
#[CoversClass(WebhookDeliveryResource::class)]
#[CoversClass(WebhookEndpointPolicy::class)]
#[CoversClass(ApiResponse::class)]
final class TestWebhookEndpointControllerTest extends TestCase
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
     * Queue a signed ping delivery through the production path.
     */
    #[Test]
    public function it_queues_a_signed_test_ping(): void
    {
        // Arrange

        Http::fake(['*' => Http::response('ok', 200)]);

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create(['url' => 'https://example.com/hooks']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson("/api/webhook-endpoints/{$endpoint->id}/test");

        // Assert

        $response->assertStatus(202);
        $response->assertJsonPath('message', 'Webhook Test Ping Queued Successfully');

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'webhook.ping',
            'status' => 'delivered',
        ]);

        Http::assertSent(function (Request $httpRequest): bool {
            $rawSignature = $httpRequest->header('Webhook-Signature')[0] ?? '';
            $signature = is_string($rawSignature) ? $rawSignature : '';

            return $httpRequest->hasHeader('Webhook-Id')
                && str_starts_with($signature, 'v1,')
                && $httpRequest->header('Webhook-Event') === ['webhook.ping'];
        });
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
        $response = $this->actingAs($manager)->postJson("/api/webhook-endpoints/{$endpoint->id}/test");

        // Assert

        $response->assertForbidden();
    }

    /*
     * Authentication Tests
     * --------------------
     */

    /**
     * Deny unauthenticated callers.
     */
    #[Test]
    public function it_denies_unauthenticated_callers(): void
    {
        // Arrange

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson("/api/webhook-endpoints/{$endpoint->id}/test");

        // Assert

        $response->assertUnauthorized();
    }
}
