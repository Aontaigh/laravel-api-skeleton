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
     * Live bearer tokens issued under the old secret stay valid after
     * rotation - this is deliberate, reviewed design, not an oversight, so
     * do not "fix" it here: revoking on rotate would break the OAuth2
     * client-credentials convention every mainstream provider follows
     * (Google, Microsoft, GitHub all leave issued tokens to ride their
     * expiry) and turn every routine rotation into an integration outage.
     * The rotation itself is audited; the compromise runbook is deactivate
     * the client (which kills every token), rotate, then re-enable.
     *
     * The active flag is left alone on purpose too: rotation must never
     * silently resume a deactivated client, and must never silently break
     * one either - operators pause with `PATCH … {"is_active": false}` and
     * resume explicitly.
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
