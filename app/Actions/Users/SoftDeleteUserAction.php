<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Auth\LogoutUserAction;
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
     * @param LogoutUserAction $logoutUser revokes tokens, sessions, and remember-me state
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
     * Revoke every credential, then soft-delete the given User.
     *
     * `delete()` alone only stamps `deleted_at`; access is denied solely by the
     * user provider excluding trashed models, so tokens and registry rows would
     * otherwise stay valid. Credentials are revoked first, in the same
     * transaction, so a failure leaves the account disabled rather than
     * deleted with live credentials.
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

            $user->delete();
        });
    }
}
