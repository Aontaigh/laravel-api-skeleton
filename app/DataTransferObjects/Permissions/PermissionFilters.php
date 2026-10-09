<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Permissions;

use Carbon\CarbonImmutable;

/**
 * Validated filter inputs for Permission list queries.
 */
final readonly class PermissionFilters
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new PermissionFilters value object.
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
