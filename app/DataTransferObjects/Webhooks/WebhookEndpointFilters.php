<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Webhooks;

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
     * @param string|null $search partial name or URL search term, or null when omitted
     */
    public function __construct(
        public ?string $search = null,
    ) {}
}
