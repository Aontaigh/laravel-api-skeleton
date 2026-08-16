<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds the application's database with roles, permissions, and demo Users.
 */
final class DatabaseSeeder extends Seeder
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Run the seeder.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $team = Team::factory()->create(['name' => 'Acme Corp']);

        User::factory()->for($team)->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        User::factory()->for($team)->manager()->create([
            'name' => 'Manager User',
            'email' => 'manager@example.com',
        ]);

        User::factory()->for($team)->user()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Demo accounts intentionally omit mfa_method so local login stays
        // password-only. New registrations and admin-created users auto-enrol.

        $this->call(ApiClientsSeeder::class);
    }
}
