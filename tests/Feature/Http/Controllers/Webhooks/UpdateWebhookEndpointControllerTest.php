<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Webhooks;

use App\Actions\Webhooks\UpdateWebhookEndpointAction;
use App\DataTransferObjects\Webhooks\UpdateWebhookEndpointData;
use App\Enums\WebhookEvent;
use App\Http\Controllers\Webhooks\UpdateWebhookEndpointController;
use App\Http\Requests\Webhooks\UpdateWebhookEndpointRequest;
use App\Http\Resources\WebhookEndpointResource;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Policies\WebhookEndpointPolicy;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AssertsApiEnvelope;
use Tests\Concerns\FakesWebhookDns;
use Tests\TestCase;

/**
 * Feature tests for webhook endpoint update.
 */
#[CoversClass(UpdateWebhookEndpointController::class)]
#[CoversClass(UpdateWebhookEndpointRequest::class)]
#[CoversClass(UpdateWebhookEndpointAction::class)]
#[CoversClass(UpdateWebhookEndpointData::class)]
#[CoversClass(WebhookEndpointResource::class)]
#[CoversClass(WebhookEndpointPolicy::class)]
#[CoversClass(ApiResponse::class)]
final class UpdateWebhookEndpointControllerTest extends TestCase
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
     * Update an endpoint's name and events.
     */
    #[Test]
    public function it_updates_an_endpoint(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson("/api/webhook-endpoints/{$endpoint->id}", [
            'name' => 'Billing V2',
            'events' => [WebhookEvent::UserSuspended->value],
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Webhook Endpoint Updated Successfully');
        $response->assertJsonPath('data.name', 'Billing V2');
        $response->assertJsonPath('data.events', [WebhookEvent::UserSuspended->value]);
    }

    /**
     * Re-screen a changed URL and refuse private targets.
     */
    #[Test]
    public function it_rescreens_a_changed_url(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson("/api/webhook-endpoints/{$endpoint->id}", [
            'url' => 'https://10.0.0.5/hooks',
        ]);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['url']);
    }

    /**
     * Clear the failure streak when an endpoint is re-enabled.
     */
    #[Test]
    public function it_clears_the_failure_streak_on_reenable(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->disabled()->create(['failure_streak' => 10]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson("/api/webhook-endpoints/{$endpoint->id}", [
            'is_active' => true,
        ]);

        // Assert

        $response->assertOk();

        $fresh = $endpoint->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(0, $fresh->failure_streak);
        $this->assertNull($fresh->disabled_at);
    }

    /**
     * Reject an empty update body.
     */
    #[Test]
    public function it_rejects_an_empty_update_body(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson("/api/webhook-endpoints/{$endpoint->id}", []);

        // Assert

        $response->assertUnprocessable();
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
        $response = $this->actingAs($admin)->patchJson('/api/webhook-endpoints/999999', [
            'name' => 'Ghost',
        ]);

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
        $response = $this->patchJson("/api/webhook-endpoints/{$endpoint->id}", ['name' => 'Ghost']);

        // Assert

        $response->assertUnauthorized();
    }

    /*
     * Authorization Tests
     * -------------------
     */

    /**
     * Deny Managers: they hold no `webhooks.*` permission.
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
        $response = $this->actingAs($manager)->patchJson("/api/webhook-endpoints/{$endpoint->id}", [
            'name' => 'Ghost',
        ]);

        // Assert

        $response->assertForbidden();
    }
}
