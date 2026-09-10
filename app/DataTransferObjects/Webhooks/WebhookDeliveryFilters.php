<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Webhooks;

/**
 * Validated delivery index filter parameters for one endpoint.
 */
final readonly class WebhookDeliveryFilters
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create new WebhookDeliveryFilters.
     *
     * @param string|null $event  the event identifier filter, or null when omitted
     * @param string|null $status the delivery status filter, or null when omitted
     */
    public function __construct(
        public ?string $event = null,
        public ?string $status = null,
    ) {}
}
