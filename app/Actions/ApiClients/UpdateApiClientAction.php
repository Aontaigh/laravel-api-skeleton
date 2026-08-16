<?php

declare(strict_types=1);

namespace App\Actions\ApiClients;

use App\DataTransferObjects\ApiClients\UpdateApiClientData;
use App\Models\ApiClient;
use App\Services\Permissions\PermissionAbilityCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Updates an API client's mutable fields and syncs the service User name.
 */
final class UpdateApiClientAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new UpdateApiClientAction.
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
     * Apply the requested updates to the API client.
     *
     * @param  ApiClient           $client the client to update
     * @param  UpdateApiClientData $data   the validated update payload
     * @return ApiClient           the refreshed client
     */
    public function execute(ApiClient $client, UpdateApiClientData $data): ApiClient
    {
        $attributes = [];

        if ($data->name !== null) {
            $attributes['name'] = $data->name;
        }

        if ($data->abilities !== null) {
            $attributes['abilities'] = $this->abilityCatalog->normalizeTokenAbilities($data->abilities);
        }

        if ($data->isActive !== null) {
            $attributes['is_active'] = $data->isActive;
        }

        DB::transaction(function () use ($client, $attributes): void {
            if ($attributes !== []) {
                $client->forceFill($attributes)->save();
            }

            /*
             * When the client name changes, keep the linked service
             * User name in sync for audit-log readability.
             */
            if (isset($attributes['name'])) {
                $client->user->forceFill(['name' => $attributes['name']])->save();
            }
        });

        return $client->refresh();
    }
}
