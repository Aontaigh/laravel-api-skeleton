<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Factory for creating ApiClient model instances in tests.
 *
 * @extends Factory<ApiClient>
 */
final class ApiClientFactory extends Factory
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @var class-string<ApiClient> */
    protected $model = ApiClient::class;

    /** @var string|null the plaintext secret for the most recently created client */
    private static ?string $plainTextSecret = null;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed> the default ApiClient attributes
     */
    public function definition(): array
    {
        $plainSecret = Str::random(40);
        self::$plainTextSecret = $plainSecret;

        return [
            'user_id' => User::factory()->serviceAccount()->service(),
            'name' => fake()->words(asText: true),
            'client_id' => (string) Str::uuid(),
            'client_secret' => Hash::make($plainSecret),
            'abilities' => ['users.list'],
            'is_active' => true,
            'last_used_at' => null,
        ];
    }

    /**
     * Indicate that the client is deactivated.
     *
     * @return static the factory with the inactive state applied
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Return the plaintext secret from the last factory definition call.
     */
    public static function plainTextSecret(): ?string
    {
        return self::$plainTextSecret;
    }
}
