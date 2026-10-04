<?php

declare(strict_types=1);

namespace App\Support\Auth;

/**
 * Cap password field length before expensive hash verification.
 *
 * Long passwords are rejected at validation so Argon2id cannot be abused for
 * CPU exhaustion on login and other `current_password` checks.
 *
 * This is a *creation-time* cap: it belongs on registration, password reset,
 * and self-service password change, where the app is choosing a new
 * credential. It must never reach a verification input (sign-in, or a
 * `current_password` check), because lowering the configured value would then
 * lock out every account already holding a longer stored password - the
 * credential would be valid while the form refused to check it. Verification
 * paths use `PasswordByteLength`, a fixed hasher boundary that never moves.
 */
final class PasswordMaxLength
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * Title Case validation copy for `max` rule failures.
     *
     * Wording keeps the exact request field name (`php-validation-responses`
     * forbids pretty labels) and pairs with the MAX_LENGTH bound above, so the
     * limit and the copy the client sees cannot drift apart.
     */
    public const MESSAGE = 'Password Is Too Long';

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Read the configured maximum password length.
     *
     * @return int the maximum number of characters accepted in a password field
     */
    public static function value(): int
    {
        return config()->integer('api.password_max_length');
    }

    /**
     * Build the Laravel `max` validation rule for password fields.
     *
     * @return string the `max:{n}` rule string
     */
    public static function rule(): string
    {
        return 'max:'.self::value();
    }
}
