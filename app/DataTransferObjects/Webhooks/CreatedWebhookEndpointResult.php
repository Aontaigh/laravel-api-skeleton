<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Webhooks;

use App\Models\WebhookEndpoint;

/**
 * Result of creating a webhook endpoint, including the one-time plaintext secret.
 */
final readonly class CreatedWebhookEndpointResult
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new CreatedWebhookEndpointResult value object.
     *
     * @param WebhookEndpoint $endpoint        the persisted endpoint row
     * @param string          $plainTextSecret the one-time plaintext signing secret
     */
    public function __construct(
        public WebhookEndpoint $endpoint,
        public string $plainTextSecret,
    ) {}
}
