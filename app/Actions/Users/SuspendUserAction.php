<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

/**
 * Suspends a User's account.
 */
final class SuspendUserAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Mark the User as suspended.
     *
     * `suspended_at` is not mass-assignable, so it is set with `forceFill`.
     *
     * @example
     * app(SuspendUserAction::class)->execute($user);
     *
     * @param User $user the User to suspend
     */
    public function execute(User $user): void
    {
        $user->forceFill(['suspended_at' => now()])->save();
    }
}
