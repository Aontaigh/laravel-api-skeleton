<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds a demo API client for local exploration and OpenAPI verification.
 */
final class ApiClientsSeeder extends Seeder
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** Public client id for the seeded demo integration. */
    public const DEMO_CLIENT_ID = 'demo-integration-client';

    /** Default plaintext secret for the seeded demo integration (local only). */
    public const DEMO_CLIENT_SECRET = 'DemoClientSecret12';

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    public function run(): void
    {
        $plainSecret = config()->string('api.demo_client_secret');

        /** @var User $user */
        $user = User::query()->firstOrCreate(
            ['email' => 'integrations@clients.internal'],
            [
                'name' => 'Integrations Service',
                'password' => Hash::make(Str::random(64)),
                'is_service_account' => true,
                'team_id' => null,
            ],
        );

        $user->forceFill(['is_service_account' => true])->save();

        if (! $user->hasRole(RoleName::Service->value)) {
            $user->assignRole(RoleName::Service->value);
        }

        ApiClient::query()->updateOrCreate(
            ['client_id' => self::DEMO_CLIENT_ID],
            [
                'user_id' => $user->id,
                'name' => 'Demo Integration',
                'client_secret' => Hash::make($plainSecret),
                'abilities' => ['users.list', 'users.list-all', 'roles.list'],
                'is_active' => true,
            ],
        );
    }
}
