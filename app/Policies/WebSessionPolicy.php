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
