<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuthAuditLog;
use App\Models\User;

/**
 * Authorisation rules for auth audit log read endpoints.
 *
 * Admin-only for now - interactive Admins may list audit rows; other roles,
 * service accounts, and scoped tokens cannot reach this endpoint even when a
 * permission is mis-assigned.
 */
final class AuthAuditLogPolicy
{
    /**
     * The permission gating the read-only Auth Audit Log surface.
     *
     * A constant rather than an inline literal so the permission is greppable outward from the
     * Policy: the seeder, the tests, and the docs all name the same string, and a rename cannot
     * leave one of them behind.
     */
    public const LIST_PERMISSION = 'audit-logs.list';

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the User may list authentication audit logs.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User may list Auth Audit Logs
     */
    public function viewAny(User $user): bool
    {
        return $this->isAdminViewer($user);
    }

    /**
     * Whether the User may view a single audit log row.
     *
     * @param  User         $user the authenticated User
     * @param  AuthAuditLog $log  the audit row being viewed
     * @return bool         true when the User may view the Auth Audit Log
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
     *
     * The permission alone is not enough. A service account is a machine identity whose own audit
     * trail this view exposes, so it may never read the audit log even if a mis-assignment grants
     * it the permission. That is why the tests here grant the permission to a service account
     * *before* asserting the refusal: without the grant, the role matrix alone would produce the
     * same 403 and the guard would be unpinned.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User is an interactive Admin
     */
    private function isAdminViewer(User $user): bool
    {
        return $user->can(self::LIST_PERMISSION) && ! $user->isServiceAccount();
    }
}
