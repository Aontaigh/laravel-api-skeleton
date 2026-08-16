<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Restores a User from the web guard session or remember-me cookie.
 */
final class RestoreUserFromRememberAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the User from an active session or remember-me cookie.
     *
     * SessionGuard::user() resolves the session id first, then lazily authenticates
     * from the remember-me recaller cookie when the session is empty.
     *
     * @example
     * $user = app(RestoreUserFromRememberAction::class)->execute();
     *
     * @return User|null the restored User, or null when no session exists
     */
    public function execute(): ?User
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        return $user;
    }
}
