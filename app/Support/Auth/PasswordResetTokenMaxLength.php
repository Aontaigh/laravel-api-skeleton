<?php

declare(strict_types=1);

namespace App\Support\Auth;

/**
 * Cap password-reset token length before hash comparison.
 *
 * Laravel issues 64-character password-reset tokens. Bounding the raw token
 * before comparison keeps an oversized value out of the broker lookup; the
 * database column stores the hash, not the token.
 */
final class PasswordResetTokenMaxLength
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Title Case validation copy for `max` rule failures.
     */
    public const MESSAGE = 'Reset Token Is Too Long';

    /**
     * Read the configured maximum reset-token length.
     *
     * @return int the maximum number of characters accepted in a reset-token field
     */
    public static function value(): int
    {
        return config()->integer('api.password_reset_token_max_length');
    }

    /**
     * Build the Laravel `max` validation rule for reset-token fields.
     *
     * @return string the `max:{n}` rule string
     */
    public static function rule(): string
    {
        return 'max:'.self::value();
    }
}
