<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

/**
 * Restores a soft-deleted User.
 */
final class RestoreUserAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Clear the User's soft-delete marker.
     *
     * Restoring returns the account to the directory but grants no access on
     * its own: deletion revoked every token and session and bumped the
     * `session_version`, so a restored account must sign in again. The unique
     * index on `users.email` keeps the address reserved while the row is
     * trashed, which is why restore - not re-registration - is the supported
     * route back for a deleted account.
     *
     * @example
     * app(RestoreUserAction::class)->execute($user);
     *
     * @param  User $user the trashed User to restore
     * @return void
     */
    public function execute(User $user): void
    {
        $user->restore();
    }
}
