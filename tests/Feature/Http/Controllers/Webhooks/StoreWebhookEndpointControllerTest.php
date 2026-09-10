<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Webhooks;

use App\Actions\Webhooks\CreateWebhookEndpointAction;
use App\DataTransferObjects\Webhooks\CreatedWebhookEndpointResult;
use App\DataTransferObjects\Webhooks\CreateWebhookEndpointData;
use App\Enums\WebhookEvent;
use App\Http\Controllers\Webhooks\StoreWebhookEndpointController;
use App\Http\Requests\Webhooks\StoreWebhookEndpointRequest;
use App\Http\Resources\WebhookEndpointResource;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Policies\WebhookEndpointPolicy;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AssertsApiEnvelope;
use Tests\Concerns\FakesWebhookDns;
use Tests\TestCase;

/**
 * Feature tests for webhook endpoint creation.
 */
#[CoversClass(StoreWebhookEndpointController::class)]
#[CoversClass(StoreWebhookEndpointRequest::class)]
#[CoversClass(CreateWebhookEndpointAction::class)]
#[CoversClass(CreateWebhookEndpointData::class)]
#[CoversClass(CreatedWebhookEndpointResult::class)]
#[CoversClass(WebhookEndpointResource::class)]
#[CoversClass(WebhookEndpointPolicy::class)]
#[CoversClass(ApiResponse::class)]
final class StoreWebhookEndpointControllerTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AssertsApiEnvelope;
    use FakesWebhookDns;
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Seed roles and fake DNS for the SSRF screen.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->fakeWebhookDns(['example.com' => ['93.184.216.34']]);
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
     * Create an endpoint and return the plaintext secret exactly once.
     */
    #[Test]
    public function it_creates_an_endpoint_and_returns_the_secret_once(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/webhook-endpoints', [
            'name' => 'Billing Sync',
            'url' => 'https://example.com/hooks',
            'events' => [WebhookEvent::UserCreated->value],
        ]);

        // Assert

        $response->assertCreated();
        $response->assertJsonPath('message', 'Webhook Endpoint Created Successfully');
        $response->assertJsonPath('data.endpoint.name', 'Billing Sync');
        $response->assertJsonStructure(['data' => ['endpoint' => ['id'], 'webhook_secret']]);

        /** @var string $plainTextSecret */
        $plainTextSecret = $response->json('data.webhook_secret');

        $this->assertSame(40, strlen($plainTextSecret));

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::query()->where('name', 'Billing Sync')->firstOrFail();

        $this->assertNotSame($plainTextSecret, $endpoint->getAttributes()['secret']);

        $storedSecret = $endpoint->getAttributes()['secret'];

        $this->assertIsString($storedSecret);
        $this->assertSame($plainTextSecret, Crypt::decryptString($storedSecret));
        $this->assertSame([WebhookEvent::UserCreated->value], $endpoint->events);
        $this->assertSame($admin->id, $endpoint->user_id);
    }

    /**
     * Refuse a cloud-metadata target from the SSRF screen.
     */
    #[Test]
    public function it_refuses_a_metadata_service_target(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/webhook-endpoints', [
            'name' => 'Metadata Probe',
            'url' => 'https://169.254.169.254/latest',
            'events' => [WebhookEvent::UserCreated->value],
        ]);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['url']);
        $this->assertDatabaseMissing('webhook_endpoints', ['name' => 'Metadata Probe']);
    }

    /**
     * Refuse an unresolvable hostname.
     */
    #[Test]
    public function it_refuses_an_unresolvable_hostname(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/webhook-endpoints', [
            'name' => 'Nowhere',
            'url' => 'https://missing.example/hooks',
            'events' => [WebhookEvent::UserCreated->value],
        ]);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['url']);
    }

    /**
     * Reject unknown event identifiers.
     */
    #[Test]
    public function it_rejects_unknown_event_identifiers(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/webhook-endpoints', [
            'name' => 'Billing Sync',
            'url' => 'https://example.com/hooks',
            'events' => ['user.exploded'],
        ]);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['events.0']);
    }

    /**
     * Require at least one subscribed event.
     */
    #[Test]
    public function it_requires_at_least_one_event(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/webhook-endpoints', [
            'name' => 'Billing Sync',
            'url' => 'https://example.com/hooks',
            'events' => [],
        ]);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['events']);
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
        $response = $this->postJson('/api/webhook-endpoints', [
            'name' => 'Billing Sync',
            'url' => 'https://example.com/hooks',
            'events' => [WebhookEvent::UserCreated->value],
        ]);

        // Assert

        $response->assertUnauthorized();
    }

    /*
     * Authorization Tests
     * -------------------
     */

    /**
     * Deny Managers, Users, and service accounts: endpoints are Admin-managed.
     */
    #[Test]
    public function it_denies_non_admin_callers(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();

        /** @var User $user */
        $user = User::factory()->user()->create();

        /** @var User $serviceUser */
        $serviceUser = User::factory()->serviceAccount()->service()->create();

        $payload = [
            'name' => 'Billing Sync',
            'url' => 'https://example.com/hooks',
            'events' => [WebhookEvent::UserCreated->value],
        ];

        // Act + Assert

        $this->actingAs($manager)->postJson('/api/webhook-endpoints', $payload)->assertForbidden();
        $this->actingAs($user)->postJson('/api/webhook-endpoints', $payload)->assertForbidden();
        $this->actingAs($serviceUser)->postJson('/api/webhook-endpoints', $payload)->assertForbidden();
    }
}
