<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

/**
 * Authorisation rules for Team endpoints.
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
     * @param  Team $model the Team being viewed
     * @return bool true when the User may view that record
     */
    public function view(User $user, Team $model): bool
    {
        return $user->can('teams.list');
    }

    /**
     * Whether the User may create Teams.
     *
     * Requires `teams.create`. Only Admins hold this permission.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User may create a Team
     */
    public function create(User $user): bool
    {
        return $user->can('teams.create');
    }

    /**
     * Whether the User may update a Team record.
     *
     * Requires `teams.update`. Only Admins hold this permission.
     *
     * @param  User $user  the authenticated User
     * @param  Team $model the Team being updated
     * @return bool true when the User may update that record
     */
    public function update(User $user, Team $model): bool
    {
        return $user->can('teams.update');
    }

    /**
     * Whether the User may delete a Team record.
     *
     * Requires `teams.delete`. Only Admins hold this permission. The
     * assigned-Users guard lives in `DeleteTeamAction`, not here: authorisation
     * answers who may delete, the Action answers what may be deleted.
     *
     * @param  User $user  the authenticated User
     * @param  Team $model the Team being deleted
     * @return bool true when the User may delete that record
     */
    public function delete(User $user, $model): bool
    {
        return $user->can('teams.delete');
    }
}
