<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Webhooks;

use App\Models\WebhookEndpoint;

/**
 * Result of rotating a webhook endpoint secret, including the one-time plaintext secret.
 */
final readonly class RotatedWebhookSecretResult
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new RotatedWebhookSecretResult value object.
     *
     * @param WebhookEndpoint $endpoint        the endpoint row carrying the new secret
     * @param string          $plainTextSecret the one-time plaintext signing secret
     */
    public function __construct(
        public WebhookEndpoint $endpoint,
        public string $plainTextSecret,
    ) {}
}
