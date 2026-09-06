<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a successful password change originated.
 *
 * Drives the wording of the password-changed security alert so a User can
 * tell an in-app change from a reset-link recovery.
 */
enum PasswordChangeSource
{
    /** A change the User made themselves while authenticated. */
    case SelfService;

    /** A recovery made through the unauthenticated password reset flow. */
    case ResetLink;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Get the human-readable Title Case phrase for the security e-mail body.
     *
     * @return string the display label
     */
    public function label(): string
    {
        return match ($this) {
            self::SelfService => 'Your Account Security Settings',
            self::ResetLink => 'A Password Reset Link',
        };
    }
}
