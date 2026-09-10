<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for creating WebhookDelivery model instances in tests.
 *
 * @extends Factory<WebhookDelivery>
 */
final class WebhookDeliveryFactory extends Factory
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed> the default delivery attributes
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'event' => WebhookEvent::UserCreated->value,
            'payload' => ['id' => 1],
            'status' => WebhookDeliveryStatus::Pending,
            'attempts' => 0,
            'next_retry_at' => null,
            'last_status_code' => null,
            'last_error' => null,
            'delivered_at' => null,
        ];
    }
}
