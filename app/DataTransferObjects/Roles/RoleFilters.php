<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Roles;

use Carbon\CarbonImmutable;

/**
 * Validated filter inputs for Role list queries.
 */
final readonly class RoleFilters
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new RoleFilters value object.
     *
     * @param string|null          $search optional name search term
     * @param CarbonImmutable|null $from   optional inclusive `created_at` lower bound
     * @param CarbonImmutable|null $to     optional inclusive `created_at` upper bound
     */
    public function __construct(
        public ?string $search = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
    ) {}
}
