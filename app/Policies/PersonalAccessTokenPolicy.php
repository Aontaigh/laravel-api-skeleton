<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Authorisation rules for Sanctum Personal Access Tokens.
 */
final class PersonalAccessTokenPolicy
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the User may list their own Tokens.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User may view their own Token list
     */
    public function viewAny(User $user): bool
    {
        return $user->can('tokens.list-own');
    }

    /**
     * Whether the User may create a Token for themselves.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User may create their own Token
     */
    public function create(User $user): bool
    {
        return $user->can('tokens.create-own');
    }

    /**
     * Whether the User may revoke the given Token.
     *
     * Combines the permission with an ownership check — `tokens.revoke-own`
     * only ever authorises revoking a Token that belongs to the caller.
     *
     * @param  User                $user  the authenticated User
     * @param  PersonalAccessToken $token the Token being revoked
     * @return bool                true when the User may revoke that Token
     */
    public function delete(User $user, PersonalAccessToken $token): bool
    {
        if (! $user->can('tokens.revoke-own')) {
            return false;
        }

        return $token->tokenable_type === User::class && $token->tokenable_id === $user->id;
    }

    /**
     * Whether the User may create a Token on behalf of another User.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User may issue Tokens for other Users
     */
    public function createForUser(User $user): bool
    {
        return $user->can('tokens.create-for-user');
    }
}
