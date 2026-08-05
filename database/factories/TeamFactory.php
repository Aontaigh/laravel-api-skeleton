<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for creating Team model instances in tests.
 *
 * @extends Factory<Team>
 */
final class TeamFactory extends Factory
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed> the default Team attributes
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
        ];
    }
}
