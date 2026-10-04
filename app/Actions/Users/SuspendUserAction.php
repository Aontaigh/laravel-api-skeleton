<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Auth\LogoutUserAction;
use App\Actions\Auth\RevokePasswordResetTokensAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Suspends a User's account.
 */
final class SuspendUserAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new SuspendUserAction.
     *
     * @param LogoutUserAction                $logoutUser                revokes tokens, remember-me state, and sessions
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
     * Mark the User as suspended and end every active authentication session.
     *
     * `suspended_at` is not mass-assignable, so it is set with `forceFill`.
     * Token revocation and the `session_version` bump run in the same
     * transaction so a suspended User cannot keep using a Bearer credential
     * that outlived the suspension marker. This mirrors Microsoft Entra,
     * which instructs administrators to revoke refresh tokens when a user
     * is disabled, so outstanding credential grants do not outlive the
     * account-state change.
     *
     * @example
     * app(SuspendUserAction::class)->execute($user);
     *
     * @param  User $user the User to suspend
     * @return void
     */
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->forceFill(['suspended_at' => now()])->save();

            $this->logoutUser->execute($user);

            /*
             * A suspended User cannot start a password reset to regain
             * access, so a link requested before the suspension is revoked
             * in the same transaction - a pending credential-grant surface
             * must not outlive the suspension marker.
             */
            $this->revokePasswordResetTokens->execute($user);
        });
    }
}
