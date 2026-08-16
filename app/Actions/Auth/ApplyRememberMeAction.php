<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Applies Laravel remember-me state to a User.
 */
final class ApplyRememberMeAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Rotate the remember-me token on the User record.
     *
     * @example
     * app(ApplyRememberMeAction::class)->execute($user);
     *
     * @param User $user the User opting into remember-me
     */
    public function execute(User $user): void
    {
        $user->setRememberToken(Str::random(60));
        $user->save();
    }
}
