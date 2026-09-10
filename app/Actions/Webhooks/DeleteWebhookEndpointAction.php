<?php

declare(strict_types=1);

namespace App\Actions\Webhooks;

use App\Models\WebhookEndpoint;

/**
 * Deletes a webhook endpoint and its delivery history.
 */
final class DeleteWebhookEndpointAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Delete the given endpoint.
     *
     * Delivery rows go with it via the foreign-key cascade: history belongs
     * to the subscription, and keeping rows for a deleted endpoint would leave
     * unfiltered orphans in the deliveries index.
     *
     * @example
     * app(DeleteWebhookEndpointAction::class)->execute($endpoint);
     *
     * @param  WebhookEndpoint $endpoint the endpoint to delete
     * @return void
     */
    public function execute(WebhookEndpoint $endpoint): void
    {
        $endpoint->delete();
    }
}
