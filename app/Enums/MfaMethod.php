<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The multi-factor channels a User may enrol in.
 *
 * Only `Email` is wired up today; a disabled account carries a null
 * `mfa_method` rather than an enum case.
 */
enum MfaMethod: string
{
    case Email = 'email';

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Get the human-readable Title-Case label for the method.
     *
     * @return string the display label
     */
    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
        };
    }
}
