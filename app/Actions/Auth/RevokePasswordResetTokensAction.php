<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes any outstanding password reset token for a User.
 */
final class RevokePasswordResetTokensAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Delete the User's row from the password broker's token storage.
     *
     * The `password_reset_tokens` table is keyed by e-mail: a requested-but-
     * unconsumed reset link stops resolving the moment the row is deleted.
     * The delete is idempotent - a User with no outstanding link is
     * unaffected.
     *
     * Callers are the account-lifecycle transitions that change what the
     * account is or whether it exists at all: role changes, suspension, and
     * soft-deletion. Self-service logout is deliberately not a caller - ending
     * a browser session must not kill a reset link the same person may still
     * need.
     *
     * This follows mainstream practice: Microsoft Entra instructs
     * administrators to revoke refresh tokens when disabling, deleting, or
     * role-changing a user, so outstanding credential grants do not outlive
     * the account-state change, and it runs that revocation in the same
     * operation. Google Cloud IAM applies the same principle from the other
     * side: service accounts have no password at all, so password reset is
     * exclusively for interactive users, and revoking the interactive
     * credential surface on a transition to a machine identity is what keeps
     * that boundary honest.
     *
     * @example
     * app(RevokePasswordResetTokensAction::class)->execute($user);
     *
     * @param  User $user the User whose outstanding reset token is revoked
     * @return void
     */
    public function execute(User $user): void
    {
        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->delete();
    }
}
