<?php

declare(strict_types=1);

namespace App\Actions\Webhooks;

use App\DataTransferObjects\Webhooks\RotatedWebhookSecretResult;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

/**
 * Rotates a webhook endpoint's signing secret.
 */
final class RotateWebhookEndpointSecretAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Replace the endpoint secret with a one-time plaintext value.
     *
     * The failure streak resets (a rotation usually follows incident
     * response), but the active flag is left alone: rotating must never
     * silently resume a deliberately paused or auto-disabled endpoint - the
     * operator re-enables explicitly with `PATCH … {"is_active": true}`.
     *
     * @example
     * $result = app(RotateWebhookEndpointSecretAction::class)->execute($endpoint);
     *
     * @param  WebhookEndpoint            $endpoint the endpoint losing its secret
     * @return RotatedWebhookSecretResult the endpoint row and plaintext secret
     */
    public function execute(WebhookEndpoint $endpoint): RotatedWebhookSecretResult
    {
        $plainSecret = Str::random(40);

        $endpoint->forceFill([
            'secret' => $plainSecret,
            'failure_streak' => 0,
            'disabled_at' => null,
        ])->save();

        return new RotatedWebhookSecretResult($endpoint->refresh(), $plainSecret);
    }
}
