<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalises email addresses before validation and persistence.
 */
final class EmailAddress
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Store an email address in lowercase.
     *
     * @param  string $email the raw email address
     * @return string the normalised email address
     */
    public static function normalise(string $email): string
    {
        return strtolower($email);
    }
}
