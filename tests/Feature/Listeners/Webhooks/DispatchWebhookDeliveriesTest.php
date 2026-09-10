<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners\Webhooks;

use App\Enums\WebhookEvent;
use App\Events\WebhookEventDispatched;
use App\Jobs\Webhooks\DeliverWebhookJob;
use App\Listeners\Webhooks\DispatchWebhookDeliveries;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for webhook event fan-out.
 */
#[CoversClass(DispatchWebhookDeliveries::class)]
#[CoversClass(WebhookEventDispatched::class)]
final class DispatchWebhookDeliveriesTest extends TestCase
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
     * Seed roles for factory-built owners.
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

    /**
     * Create one pending delivery per subscribed endpoint and queue its send.
     */
    #[Test]
    public function it_fans_out_one_pending_delivery_per_subscribed_endpoint(): void
    {
        // Arrange

        Queue::fake([DeliverWebhookJob::class]);

        /** @var WebhookEndpoint $subscribed */
        $subscribed = WebhookEndpoint::factory()->create(['events' => [WebhookEvent::UserCreated->value]]);

        /** @var WebhookEndpoint $otherEvent */
        $otherEvent = WebhookEndpoint::factory()->create(['events' => [WebhookEvent::TeamCreated->value]]);

        /** @var WebhookEndpoint $disabled */
        $disabled = WebhookEndpoint::factory()->disabled()->create(['events' => [WebhookEvent::UserCreated->value]]);

        // Act

        event(new WebhookEventDispatched(
            event: WebhookEvent::UserCreated,
            data: ['id' => 7, 'email' => 'new@example.com'],
        ));

        // Assert

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_endpoint_id' => $subscribed->id,
            'event' => WebhookEvent::UserCreated->value,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('webhook_deliveries', ['webhook_endpoint_id' => $otherEvent->id]);
        $this->assertDatabaseMissing('webhook_deliveries', ['webhook_endpoint_id' => $disabled->id]);

        Queue::assertPushed(DeliverWebhookJob::class, 1);
    }

    /**
     * Stamp the canonical payload onto the delivery row.
     */
    #[Test]
    public function it_stamps_the_canonical_payload(): void
    {
        // Arrange

        Queue::fake([DeliverWebhookJob::class]);

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create(['events' => [WebhookEvent::UserCreated->value]]);

        // Act

        event(new WebhookEventDispatched(
            event: WebhookEvent::UserCreated,
            data: ['id' => 7],
        ));

        // Assert

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::query()
            ->where('webhook_endpoint_id', $endpoint->id)
            ->firstOrFail();

        $this->assertSame($delivery->uuid, $delivery->payload['id']);
        $this->assertSame(WebhookEvent::UserCreated->value, $delivery->payload['event']);
        $this->assertSame(['id' => 7], $delivery->payload['data']);
        $this->assertIsString($delivery->payload['occurred_at']);
    }
}
