<?php

declare(strict_types=1);

namespace App\Actions\ApiClients;

use App\DataTransferObjects\ApiClients\CreateApiClientData;
use App\DataTransferObjects\ApiClients\CreatedApiClientResult;
use App\Enums\RoleName;
use App\Models\ApiClient;
use App\Models\User;
use App\Services\Permissions\PermissionAbilityCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates a service User and linked API client credentials.
 */
final class CreateApiClientAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new Create ApiClient Action.
     *
     * @param PermissionAbilityCatalog $abilityCatalog validates abilities against the permission catalog
     */
    public function __construct(
        private readonly PermissionAbilityCatalog $abilityCatalog,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Persist a service account and API client with a one-time plaintext secret.
     *
     * @example
     * $result = app(CreateApiClientAction::class)->execute($data);
     *
     * @param  CreateApiClientData    $data the validated client payload
     * @return CreatedApiClientResult the persisted client and plaintext secret
     */
    public function execute(CreateApiClientData $data): CreatedApiClientResult
    {
        $abilities = $this->abilityCatalog->normalizeTokenAbilities($data->abilities);
        $plainSecret = Str::random(40);
        $clientId = (string) Str::uuid();
        $email = $this->generateServiceEmail($data->name);

        /*
         * The service User and its ApiClient are created in one transaction:
         * a failed client insert must not leave an orphaned service account
         * behind.
         */
        $client = DB::transaction(function () use ($data, $abilities, $plainSecret, $clientId, $email): ApiClient {
            /** @var User $user */
            $user = User::query()->create([
                'name' => $data->name,
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'is_service_account' => true,
                'team_id' => null,
            ]);

            $user->assignRole(RoleName::Service->value);

            /** @var ApiClient $client */
            $client = ApiClient::query()->create([
                'user_id' => $user->id,
                'name' => $data->name,
                'client_id' => $clientId,
                'client_secret' => Hash::make($plainSecret),
                'abilities' => $abilities,
                'is_active' => true,
            ]);

            $client->setRelation('user', $user);

            return $client;
        });

        return new CreatedApiClientResult($client, $plainSecret);
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Build a unique internal email for a service account.
     *
     * @param  string $name the client display name
     * @return string the generated email address
     */
    private function generateServiceEmail(string $name): string
    {
        $slug = Str::slug($name, '.');

        if ($slug === '') {
            $slug = 'client';
        }

        $base = Str::limit($slug, 50, '');
        $suffix = Str::lower(Str::random(8));

        return "{$base}.{$suffix}@clients.internal";
    }
}
