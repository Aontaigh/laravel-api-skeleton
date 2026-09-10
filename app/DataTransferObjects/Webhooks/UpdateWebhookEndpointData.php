<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Webhooks;

use App\Models\WebhookEndpoint;

/**
 * Validated input for updating a webhook endpoint.
 */
final readonly class UpdateWebhookEndpointData
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new UpdateWebhookEndpointData value object.
     *
     * @param WebhookEndpoint   $endpoint the endpoint being updated
     * @param string|null       $name     the new display name, or null to leave unchanged
     * @param string|null       $url      the new target URL, or null to leave unchanged
     * @param list<string>|null $events   the new subscribed events, or null to leave unchanged
     * @param bool|null         $isActive the new active flag, or null to leave unchanged
     */
    public function __construct(
        public WebhookEndpoint $endpoint,
        public ?string $name = null,
        public ?string $url = null,
        public ?array $events = null,
        public ?bool $isActive = null,
    ) {}
}
