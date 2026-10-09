<?php

declare(strict_types=1);

namespace App\DataTransferObjects\ApiClients;

use Carbon\CarbonImmutable;

/**
 * Validated filters for the API client index.
 */
final readonly class ApiClientFilters
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new ApiClientFilters value object.
     *
     * @param string|null          $search optional partial name match
     * @param CarbonImmutable|null $from   optional inclusive `created_at` lower bound
     * @param CarbonImmutable|null $to     optional inclusive `created_at` upper bound
     */
    public function __construct(
        public ?string $search = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
    ) {}
}
