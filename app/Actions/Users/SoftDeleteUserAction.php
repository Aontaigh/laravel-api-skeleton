<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

/**
 * Soft-deletes a User.
 */
final class SoftDeleteUserAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Soft-delete the given User.
     *
     * @example
     * app(SoftDeleteUserAction::class)->execute($user);
     *
     * @param User $user the User to soft-delete
     */
    public function execute(User $user): void
    {
        $user->delete();
    }
}
