<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\AuthAuditLog;
use App\Models\User;

/**
 * Authorisation rules for auth audit log read endpoints.
 *
 * Admin-only for now — interactive Admins may list audit rows; other roles,
 * service accounts, and scoped tokens cannot reach this endpoint even when a
 * permission is mis-assigned.
 */
final class AuthAuditLogPolicy
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the User may list authentication audit logs.
     *
     * @param User $user the authenticated User
     */
    public function viewAny(User $user): bool
    {
        return $this->isAdminViewer($user);
    }

    /**
     * Whether the User may view a single audit log row.
     *
     * @param User         $user the authenticated User
     * @param AuthAuditLog $log  the audit row being viewed
     */
    public function view(User $user, AuthAuditLog $log): bool
    {
        return $this->isAdminViewer($user);
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the User is an interactive Admin caller.
     */
    private function isAdminViewer(User $user): bool
    {
        return $user->hasRole(RoleName::Admin->value) && ! $user->isServiceAccount();
    }
}
