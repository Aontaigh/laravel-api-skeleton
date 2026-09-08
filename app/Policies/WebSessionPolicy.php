<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WebSession;

/**
 * Authorisation rules for registered cookie-bound web sessions.
 */
final class WebSessionPolicy
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the User may list web sessions.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User may view the session index
     */
    public function viewAny(User $user): bool
    {
        return $user->can('sessions.list-own') && ! $user->isServiceAccount();
    }

    /**
     * Whether the User may view a single web session record.
     *
     * Row scope matches revoke: holders of `sessions.list-all` see every row,
     * everyone else only their own. The scoped `{web_session}` route binding
     * already 404s out-of-scope rows, so this is the second gate, not the only
     * one - a denied viewer never learns whether the row exists.
     *
     * @param  User       $user       the authenticated User
     * @param  WebSession $webSession the session being viewed
     * @return bool       true when the User may view that session
     */
    public function view(User $user, WebSession $webSession): bool
    {
        if ($user->can('sessions.list-all')) {
            return true;
        }

        if (! $user->can('sessions.list-own') || $user->isServiceAccount()) {
            return false;
        }

        return $webSession->user_id === $user->id;
    }

    /**
     * Whether the User may revoke the given web session.
     *
     * @param  User       $user       the authenticated User
     * @param  WebSession $webSession the session being revoked
     * @return bool       true when the User may revoke that session
     */
    public function delete(User $user, WebSession $webSession): bool
    {
        if ($user->can('sessions.revoke-any')) {
            return true;
        }

        if (! $user->can('sessions.revoke-own') || $user->isServiceAccount()) {
            return false;
        }

        return $webSession->user_id === $user->id;
    }
}
