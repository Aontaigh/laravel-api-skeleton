<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Factory for creating User model instances in tests.
 *
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @var string|null the current password being used by the factory */
    protected static ?string $password = null;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed> the default User attributes
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'remember_token' => Str::random(60),
            'is_service_account' => false,
            'suspended_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static the factory with the unverified state applied
     */
    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }

    /**
     * Indicate that the User has no Team.
     *
     * @return static the factory with no Team assigned
     */
    public function withoutTeam(): static
    {
        return $this->state(['team_id' => null]);
    }

    /**
     * Indicate that the User holds the Admin Spatie role.
     *
     * Assigned via `afterCreating()` because `assignRole()` needs a
     * persisted, keyed model — a plain `state()` array cannot call it.
     *
     * @return static the factory that assigns the Admin role after creation
     */
    public function admin(): static
    {
        return $this->afterCreating(static function (User $user): void {
            $user->assignRole(RoleName::Admin->value);
        });
    }

    /**
     * Indicate that the User holds the Manager Spatie role.
     *
     * @return static the factory that assigns the Manager role after creation
     */
    public function manager(): static
    {
        return $this->afterCreating(static function (User $user): void {
            $user->assignRole(RoleName::Manager->value);
        });
    }

    /**
     * Indicate that the User holds the User Spatie role.
     *
     * @return static the factory that assigns the User role after creation
     */
    public function user(): static
    {
        return $this->afterCreating(static function (User $user): void {
            $user->assignRole(RoleName::User->value);
        });
    }

    /**
     * Indicate that the User is a non-interactive service account.
     *
     * @return static the factory with the service-account state applied
     */
    public function serviceAccount(): static
    {
        return $this->state(['is_service_account' => true]);
    }

    /**
     * Indicate that the User holds the Service Spatie role.
     *
     * @return static the factory that assigns the Service role after creation
     */
    public function service(): static
    {
        return $this->afterCreating(static function (User $user): void {
            $user->assignRole(RoleName::Service->value);
        });
    }

    /**
     * Indicate that the User's account is suspended.
     *
     * @return static the factory with the suspension marker set
     */
    public function suspended(): static
    {
        return $this->state(['suspended_at' => now()]);
    }
}
