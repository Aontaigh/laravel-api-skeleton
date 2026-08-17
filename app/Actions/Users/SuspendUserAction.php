<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Auth\LogoutUserAction;
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
     * @param LogoutUserAction $logoutUser revokes tokens, remember-me state, and sessions
     */
    public function __construct(
        private readonly LogoutUserAction $logoutUser,
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
     * that outlived the suspension marker.
     *
     * @example
     * app(SuspendUserAction::class)->execute($user);
     *
     * @param User $user the User to suspend
     */
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->forceFill(['suspended_at' => now()])->save();

            $this->logoutUser->execute($user);
        });
    }
}
