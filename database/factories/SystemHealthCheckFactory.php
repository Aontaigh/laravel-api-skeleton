<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SystemHealthStatus;
use App\Models\SystemHealthCheck;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SystemHealthCheck>
 */
final class SystemHealthCheckFactory extends Factory
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @var class-string<SystemHealthCheck> */
    protected $model = SystemHealthCheck::class;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'component' => 'database',
            'status' => SystemHealthStatus::Up,
            'response_time_ms' => fake()->numberBetween(1, 50),
            'message' => null,
            'checked_at' => Carbon::now('UTC'),
        ];
    }

    /**
     * Record the row for a specific component slug.
     *
     * @param  string $component the component slug
     * @return static
     */
    public function forComponent(string $component): static
    {
        return $this->state(fn (): array => [
            'component' => $component,
        ]);
    }

    /**
     * Record a Down outcome with a bounded diagnostic message.
     *
     * @param  string $message the bounded diagnostic message
     * @return static
     */
    public function down(string $message = 'Connection Refused'): static
    {
        return $this->state(fn (): array => [
            'status' => SystemHealthStatus::Down,
            'response_time_ms' => null,
            'message' => $message,
        ]);
    }

    /**
     * Record a Degraded outcome.
     *
     * @return static
     */
    public function degraded(): static
    {
        return $this->state(fn (): array => [
            'status' => SystemHealthStatus::Degraded,
            'response_time_ms' => fake()->numberBetween(501, 2000),
            'message' => 'Database Responded Slowly',
        ]);
    }
}
