<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Authorisation rules for Permission list endpoints.
 */
final class PermissionPolicy
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the User may list Permissions.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User may view the Permission Index
     */
    public function viewAny(User $user): bool
    {
        return $user->can('permissions.list');
    }
}
