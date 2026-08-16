<?php

declare(strict_types=1);

namespace App\DataTransferObjects\ApiClients;

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
     * @param string|null $search optional partial name match
     */
    public function __construct(
        public ?string $search = null,
    ) {}
}
