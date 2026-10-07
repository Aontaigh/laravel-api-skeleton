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
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new UserFilters value object.
     *
     * `$viewer` is required: row scoping is derived from it, so allowing
     * null would let a caller silently produce an unscoped result set.
     *
     * @param User         $viewer        the authenticated User (drives row scoping)
     * @param bool         $listsAllTeams whether the viewer may see every Team, not just their own
     * @param string|null  $search        optional name/email search term
     * @param bool         $canViewEmails whether the viewer may read User emails
     * @param list<string> $statuses      optional account status filters (`active`, `suspended`, or `deleted`)
     * @param list<string> $roles         optional role name filters (`Admin`, `Manager`, `User`, or `Service`)
     */
    public function __construct(
        public User $viewer,
        public bool $listsAllTeams = false,
        public ?string $search = null,
        public bool $canViewEmails = false,
        public array $statuses = [],
        public array $roles = [],
    ) {}
}
