<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

/**
 * Lifts a User's account suspension.
 */
final class UnsuspendUserAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Clear the User's suspension marker.
     *
     * `suspended_at` is not mass-assignable, so it is set with `forceFill`.
     *
     * @example
     * app(UnsuspendUserAction::class)->execute($user);
     *
     * @param User $user the User to unsuspend
     */
    public function execute(User $user): void
    {
        $user->forceFill(['suspended_at' => null])->save();
    }
}
