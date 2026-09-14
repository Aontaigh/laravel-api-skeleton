<?php

declare(strict_types=1);

namespace App\Actions\ApiClients;

use App\Models\ApiClient;
use Illuminate\Support\Facades\DB;

/**
 * Deactivates an API client and revokes every token on its service User.
 */
final class RevokeApiClientAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Deactivate the client and delete every Sanctum token on its service User.
     *
     * @example
     * app(RevokeApiClientAction::class)->execute($client);
     *
     * @param  ApiClient $client the client to revoke
     * @return void
     */
    public function execute(ApiClient $client): void
    {
        DB::transaction(function () use ($client): void {
            $client->forceFill([
                'is_active' => false,
            ])->save();

            $client->user->tokens()->delete();
        });
    }
}
