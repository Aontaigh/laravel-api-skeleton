<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Users;

use App\Models\User;

/**
 * Validated filter inputs for User list queries.
 */
final readonly class UserFilters
{
    /*
    |--------------------------------------------------------------------------​
    | Constructor
    |--------------------------------------------------------------------------​
    */

    /**
     * Create a new UserFilters value object.
     *
     * `$viewer` is required: row scoping is derived from it, so allowing
     * null would let a caller silently produce an unscoped result set.
     *
     * @param User        $viewer        the authenticated User (drives row scoping)
     * @param bool        $listsAllTeams whether the viewer may see every Team, not just their own
     * @param string|null $search        optional name/email search term
     */
    public function __construct(
        public User $viewer,
        public bool $listsAllTeams = false,
        public ?string $search = null,
    ) {}
}
