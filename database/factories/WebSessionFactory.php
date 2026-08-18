<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\WebSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebSession>
 */
final class WebSessionFactory extends Factory
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @var class-string<WebSession> */
    protected $model = WebSession::class;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_id' => Str::random(40),
            'device_name' => fake()->words(2, true),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'remember_me' => false,
            'last_activity_at' => now(),
            'revoked_at' => null,
        ];
    }

    /**
     * Mark the web session as revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'revoked_at' => now(),
        ]);
    }
}
