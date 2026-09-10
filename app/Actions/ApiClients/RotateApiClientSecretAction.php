<?php

declare(strict_types=1);

namespace App\Actions\ApiClients;

use App\DataTransferObjects\ApiClients\RotatedClientSecretResult;
use App\Models\ApiClient;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Rotates an API client's secret.
 */
final class RotateApiClientSecretAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Replace the client secret with a one-time plaintext value.
     *
     * The active flag is left alone on purpose: rotation must never silently
     * resume a deactivated client, and must never silently break one either -
     * operators pause with `PATCH … {"is_active": false}` and resume
     * explicitly. Live bearer tokens issued under the old secret stay valid
     * until natural expiry or deactivation (OAuth2 client-credentials
     * semantics); if the secret itself was compromised, deactivate the client
     * to kill every token, rotate, then re-enable.
     *
     * @example
     * $result = app(RotateApiClientSecretAction::class)->execute($client);
     *
     * @param  ApiClient                 $client the client losing its secret
     * @return RotatedClientSecretResult the client row and plaintext secret
     */
    public function execute(ApiClient $client): RotatedClientSecretResult
    {
        $plainSecret = Str::random(40);

        $client->forceFill([
            'client_secret' => Hash::make($plainSecret),
        ])->save();

        return new RotatedClientSecretResult($client->refresh(), $plainSecret);
    }
}
