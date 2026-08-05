<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Queries\Roles\RoleQueryConstraints;
use Spatie\Permission\Models\Role;

/**
 * Authorisation rules for Role list and show endpoints.
 */
final class RolePolicy
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the User may list Roles.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User may view the Role Index
     */
    public function viewAny(User $user): bool
    {
        return $user->can('roles.list');
    }

    /**
     * Whether the User may view a single Role record.
     *
     * Only roles for the application's default guard are exposed — the index
     * pins `guard_name` the same way so callers cannot fetch a foreign guard
     * row by id.
     *
     * @param  User $user  the authenticated User
     * @param  Role $model the Role being viewed
     * @return bool true when the User may view that record
     */
    public function view(User $user, Role $model): bool
    {
        return $user->can('roles.list')
            && $model->guard_name === RoleQueryConstraints::GUARD_NAME;
    }
}
