<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Teams;

use Carbon\CarbonImmutable;

/**
 * Validated Team Index filter parameters.
 */
final readonly class TeamFilters
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new TeamFilters.
     *
     * @param string|null          $search partial name search term, or null when omitted
     * @param CarbonImmutable|null $from   optional inclusive `created_at` lower bound
     * @param CarbonImmutable|null $to     optional inclusive `created_at` upper bound
     */
    public function __construct(
        public ?string $search = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
    ) {}
}
