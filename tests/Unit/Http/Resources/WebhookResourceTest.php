<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\WebhookDeliveryResource;
use App\Http\Resources\WebhookEndpointResource;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for webhook resource serialisation branches.
 */
#[CoversClass(WebhookEndpointResource::class)]
#[CoversClass(WebhookDeliveryResource::class)]
final class WebhookResourceTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Serialise the endpoint shape without ever exposing the secret.
     */
    #[Test]
    public function it_serialises_the_endpoint_without_the_secret(): void
    {
        // Arrange

        $endpoint = WebhookEndpoint::factory()->make([
            'user_id' => 1,
            'name' => 'Billing',
            'secret' => 'super-secret-value',
        ]);

        // Act

        /** @var array<string, mixed> $payload */
        $payload = (new WebhookEndpointResource($endpoint))->toArray(Request::create('/'));

        // Assert

        $this->assertSame('Billing', $payload['name']);
        $this->assertArrayNotHasKey('secret', $payload);
        $this->assertStringNotContainsString('super-secret-value', (string) json_encode($payload));
    }

    /**
     * Serialise the delivery shape with enum and datetime branches.
     */
    #[Test]
    public function it_serialises_the_delivery_shape(): void
    {
        // Arrange

        $delivery = WebhookDelivery::factory()->make([
            'webhook_endpoint_id' => 1,
            'uuid' => 'delivery-uuid',
            'event' => 'user.created',
            'status' => 'delivered',
            'attempts' => 1,
        ]);

        // Act

        /** @var array<string, mixed> $payload */
        $payload = (new WebhookDeliveryResource($delivery))->toArray(Request::create('/'));

        // Assert

        $this->assertSame('delivery-uuid', $payload['uuid']);
        $this->assertSame('delivered', $payload['status']);
        $this->assertSame(1, $payload['attempts']);
    }
}
