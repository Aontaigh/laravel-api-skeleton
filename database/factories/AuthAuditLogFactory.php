<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AuthAuditEvent;
use App\Models\AuthAuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuthAuditLog>
 */
final class AuthAuditLogFactory extends Factory
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @var class-string<AuthAuditLog> */
    protected $model = AuthAuditLog::class;

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
            'user_id' => null,
            'event' => AuthAuditEvent::Login,
            'email' => fake()->unique()->safeEmail(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'personal_access_token_id' => null,
            'api_client_id' => null,
            'remember_me' => false,
        ];
    }
}
