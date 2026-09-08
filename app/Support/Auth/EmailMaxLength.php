<?php

declare(strict_types=1);

namespace App\Support\Auth;

/**
 * Cap e-mail field length before database lookup or persistence.
 *
 * Matches the `users.email` and `password_reset_tokens.email` VARCHAR(255)
 * columns so oversized input is rejected at validation.
 */
final class EmailMaxLength
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Title Case validation copy for `max` rule failures.
     */
    public const MESSAGE = 'E-Mail Is Too Long';

    /**
     * Read the configured maximum e-mail length.
     *
     * @return int the maximum number of characters accepted in an e-mail field
     */
    public static function value(): int
    {
        return config()->integer('api.email_max_length');
    }

    /**
     * Build the Laravel `max` validation rule for e-mail fields.
     *
     * @return string the `max:{n}` rule string
     */
    public static function rule(): string
    {
        return 'max:'.self::value();
    }
}
