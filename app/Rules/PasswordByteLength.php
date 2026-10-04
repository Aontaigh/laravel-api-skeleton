<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Enforces a byte-level bound on the password input.
 *
 * Laravel's `max` string rule counts characters, not bytes, and a multibyte
 * password can hide far more bytes than characters. A bcrypt-family hasher
 * truncates silently at 72 bytes, so an over-long password would
 * authenticate the same as its truncated prefix - two distinct accepted
 * passwords become equivalent. This rule rejects the value when `strlen()`
 * exceeds that boundary, which is the byte length the hasher sees. The apps
 * hash with Argon2id (which has no such truncation), so the bound is a
 * defensive constant that keeps the guarantee if the hasher ever changes.
 */
final class PasswordByteLength implements ValidationRule
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * Hard bcrypt input limit in bytes.
     *
     * bcrypt ignores everything past this boundary, so a longer password is not
     * a stronger secret - it is a prefix collision.
     */
    public const int MAX_BYTES = 72;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Run the validation rule.
     *
     * @example
     * ```php
     * 'password' => ['required', 'string', new PasswordByteLength],
     * ```
     *
     * @param  string                                       $attribute the field being validated
     * @param  mixed                                        $value     the candidate password
     * @param  Closure(string): PotentiallyTranslatedString $fail      the failure callback
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || strlen($value) <= self::MAX_BYTES) {
            return;
        }

        $fail('Password Must Not Exceed 72 Bytes');
    }
}
