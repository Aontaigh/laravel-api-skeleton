<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Auth\LogoutUserAction;
use App\Actions\Auth\RevokePasswordResetTokensAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Soft-deletes a User.
 */
final class SoftDeleteUserAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new Soft Delete User Action.
     *
     * @param LogoutUserAction                $logoutUser                revokes tokens, sessions, and remember-me state
     * @param RevokePasswordResetTokensAction $revokePasswordResetTokens deletes any outstanding password reset token
     */
    public function __construct(
        private readonly LogoutUserAction $logoutUser,
        private readonly RevokePasswordResetTokensAction $revokePasswordResetTokens,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Revoke every credential, then soft-delete the given User.
     *
     * `delete()` alone only stamps `deleted_at`; access is denied solely by the
     * user provider excluding trashed models, so tokens and registry rows would
     * otherwise stay valid. Credentials are revoked first, in the same
     * transaction, so a failure leaves the account disabled rather than
     * deleted with live credentials. This mirrors Microsoft Entra, which
     * instructs administrators to revoke refresh tokens when deleting or
     * disabling a user, in the same operation, so outstanding credential
     * grants do not outlive the account-state change.
     *
     * @example
     * app(SoftDeleteUserAction::class)->execute($user);
     *
     * @param  User $user the User to soft-delete
     * @return void
     */
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->logoutUser->execute($user);

            /*
             * A soft-deleted User cannot sign in to start a password reset,
             * so a link requested before the deletion is revoked in the same
             * transaction - a pending credential-grant surface must not
             * outlive the account it was issued against.
             */
            $this->revokePasswordResetTokens->execute($user);

            $user->delete();
        });
    }
}
