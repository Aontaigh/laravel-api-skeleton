<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Webhooks;

use Carbon\CarbonImmutable;

/**
 * Validated webhook endpoint index filter parameters.
 */
final readonly class WebhookEndpointFilters
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create new WebhookEndpointFilters.
     *
     * @param string|null          $search partial name or URL search term, or null when omitted
     * @param CarbonImmutable|null $from   optional inclusive `created_at` lower bound
     * @param CarbonImmutable|null $to     optional inclusive `created_at` upper bound
     */
    public function __construct(
        public ?string $search = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
    ) {}
}
