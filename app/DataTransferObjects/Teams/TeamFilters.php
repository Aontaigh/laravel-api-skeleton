<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Teams;

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
     * @param string|null $search partial name search term, or null when omitted
     */
    public function __construct(
        public ?string $search = null,
    ) {}
}
