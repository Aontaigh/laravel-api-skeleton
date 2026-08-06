<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Roles;

/**
 * Validated filter inputs for Role list queries.
 */
final readonly class RoleFilters
{
    /*
    |--------------------------------------------------------------------------​
    | Constructor
    |--------------------------------------------------------------------------​
    */

    /**
     * Create a new RoleFilters value object.
     *
     * @param string|null $search optional name search term
     */
    public function __construct(
        public ?string $search = null,
    ) {}
}
