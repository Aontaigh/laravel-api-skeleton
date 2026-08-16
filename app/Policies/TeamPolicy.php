<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Authorisation rules for Team list and show endpoints.
 */
final class TeamPolicy
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the User may list Teams.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User may view the Team Index
     */
    public function viewAny(User $user): bool
    {
        return $user->can('teams.list');
    }

    /**
     * Whether the User may view a single Team record.
     *
     * @param  User $user  the authenticated User
     * @param  Role $model the Team being viewed
     * @return bool true when the User may view that record
     */
    public function view(User $user, $model): bool
    {
        return $user->can('teams.list');
    }
}
