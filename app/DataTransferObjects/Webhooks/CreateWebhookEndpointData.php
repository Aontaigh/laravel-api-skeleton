<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Webhooks;

/**
 * Validated input for creating a webhook endpoint.
 */
final readonly class CreateWebhookEndpointData
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new CreateWebhookEndpointData value object.
     *
     * @param int          $ownerId the owning Admin's User ID
     * @param string       $name    the endpoint display name
     * @param string       $url     the delivery target URL
     * @param list<string> $events  the subscribed event identifiers
     */
    public function __construct(
        public int $ownerId,
        public string $name,
        public string $url,
        public array $events,
    ) {}
}
