<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Permissions;

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
     * @param string|null $search optional name search term
     */
    public function __construct(
        public ?string $search = null,
    ) {}
}
