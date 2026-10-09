<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Webhooks;

use Carbon\CarbonImmutable;

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
     * @param list<string>         $events   the event identifier filters, empty when omitted
     * @param list<string>         $statuses the delivery status filters, empty when omitted
     * @param CarbonImmutable|null $from     optional inclusive `created_at` lower bound
     * @param CarbonImmutable|null $to       optional inclusive `created_at` upper bound
     */
    public function __construct(
        public array $events = [],
        public array $statuses = [],
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
    ) {}
}
