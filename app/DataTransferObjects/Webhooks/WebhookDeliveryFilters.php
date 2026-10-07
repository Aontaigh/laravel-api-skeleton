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
     * @param list<string> $events   the event identifier filters, empty when omitted
     * @param list<string> $statuses the delivery status filters, empty when omitted
     */
    public function __construct(
        public array $events = [],
        public array $statuses = [],
    ) {}
}
