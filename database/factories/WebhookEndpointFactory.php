<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WebhookEvent;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for creating WebhookEndpoint model instances in tests.
 *
 * @extends Factory<WebhookEndpoint>
 */
final class WebhookEndpointFactory extends Factory
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed> the default endpoint attributes
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'name' => fake()->words(asText: true),
            'url' => 'https://example.com/hooks/'.Str::random(8),
            'events' => [WebhookEvent::UserCreated->value],
            'secret' => Str::random(40),
            'is_active' => true,
            'failure_streak' => 0,
            'disabled_at' => null,
        ];
    }

    /**
     * Indicate that the endpoint is disabled after repeated failures.
     *
     * @return static the factory with the disabled state applied
     */
    public function disabled(): static
    {
        return $this->state([
            'is_active' => false,
            'disabled_at' => now(),
        ]);
    }
}
