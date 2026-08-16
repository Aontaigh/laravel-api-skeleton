<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Authorisation rules for User list, show, update, and delete endpoints.
 */
final class UserPolicy
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the User may list Users.
     *
     * Row scoping (own Team vs every Team) is a separate decision — see
     * `AppliesUserFilters::listsAllTeams()` and `UserFilterQuery`.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User may view the User Index
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.list');
    }

    /**
     * Whether the User may create Users.
     *
     * Requires `users.create`. Only Admins hold this permission.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User may create a User
     */
    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    /**
     * Whether the User may view their own profile via `GET /me`.
     *
     * Open to every interactive account with a bearer token. Service accounts
     * are machine identities — they use client credentials, not profile screens.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User may call `GET /me`
     */
    public function viewMe(User $user): bool
    {
        return ! $user->isServiceAccount();
    }

    /**
     * Whether the User may update their own profile via `PATCH /me`.
     *
     * Open to every interactive account with a bearer token. Service accounts
     * are machine identities — they use client credentials, not self-service
     * endpoints.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User may call `PATCH /me`
     */
    public function updateMe(User $user): bool
    {
        return ! $user->isServiceAccount();
    }

    /**
     * Whether the User may view a single User record.
     *
     * Requires `users.list`. Row scope matches the index: callers without
     * `users.list-all` may only view Users on their own Team.
     *
     * @param  User $user  the authenticated User
     * @param  User $model the User being viewed
     * @return bool true when the User may view that record
     */
    public function view(User $user, User $model): bool
    {
        if (! $user->can('users.list')) {
            return false;
        }

        if ($user->can('users.list-all')) {
            return true;
        }

        return $user->team_id === $model->team_id;
    }

    /**
     * Whether the User may update a User record.
     *
     * Requires `users.update`. Row scope matches show: callers without
     * `users.list-all` may only update Users on their own Team, including
     * their own account.
     *
     * @param  User $user  the authenticated User
     * @param  User $model the User being updated
     * @return bool true when the User may update that record
     */
    public function update(User $user, User $model): bool
    {
        if (! $user->can('users.update')) {
            return false;
        }

        if ($user->can('users.list-all')) {
            return true;
        }

        return $user->team_id === $model->team_id;
    }

    /**
     * Whether the User may reassign another User to a different Team.
     *
     * Requires `users.reassign-team`. Only Admins hold this permission.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User may change a User's `team_id`
     */
    public function reassignTeam(User $user): bool
    {
        return $user->can('users.reassign-team');
    }

    /**
     * Whether the User may force-logout other Users everywhere.
     *
     * Requires `users.force-logout`. Only Admins hold this permission.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User may terminate sessions for other Users
     */
    public function forceLogout(User $user): bool
    {
        return $user->can('users.force-logout');
    }

    /**
     * Whether the User may suspend another User's account.
     *
     * Requires `users.suspend`. Callers may never suspend their own account
     * through this endpoint — an Admin suspending themselves would have no
     * one left to lift the suspension.
     *
     * @param  User $user  the authenticated User
     * @param  User $model the User being suspended
     * @return bool true when the User may suspend that record
     */
    public function suspend(User $user, User $model): bool
    {
        if (! $user->can('users.suspend')) {
            return false;
        }

        return $user->id !== $model->id;
    }

    /**
     * Whether the User may unsuspend another User's account.
     *
     * Requires `users.suspend`. A suspended User cannot reach this endpoint
     * (the `active.account` gate blocks them first), so the only reachable
     * caller is an active Admin lifting another account's suspension.
     *
     * @param  User $user  the authenticated User
     * @param  User $model the User being unsuspended
     * @return bool true when the User may unsuspend that record
     */
    public function unsuspend(User $user, User $model): bool
    {
        if (! $user->can('users.suspend')) {
            return false;
        }

        return $user->id !== $model->id;
    }

    /**
     * Whether the User may soft-delete a User record.
     *
     * Requires `users.delete`. Row scope matches show: callers without
     * `users.list-all` may only delete Users on their own Team. Callers
     * may never delete their own account through this endpoint.
     *
     * @param  User $user  the authenticated User
     * @param  User $model the User being deleted
     * @return bool true when the User may delete that record
     */
    public function delete(User $user, User $model): bool
    {
        if (! $user->can('users.delete')) {
            return false;
        }

        if ($user->id === $model->id) {
            return false;
        }

        if ($user->can('users.list-all')) {
            return true;
        }

        return $user->team_id === $model->team_id;
    }
}
