<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\E164Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validate that the input is a canonical E.164 phone number.
 *
 * HTTP callers submit display forms (`+44 7700 900100`) that FormRequests
 * compact to canonical E.164 before validation, so by the time this rule runs
 * the value must already be compact (`+447700900100`). Anything else fails.
 */
final class E164PhoneNumber implements ValidationRule
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Validate that the input is a canonical E.164 phone number.
     *
     * @param  string  $attribute the validated field name
     * @param  mixed   $value     the validated input value
     * @param  Closure $fail      the validation failure callback
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! E164Phone::isValid($value)) {
            $fail($this->errorMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Return the standard E.164 validation failure message.
     *
     * @return string the Title Case validation message
     */
    private function errorMessage(): string
    {
        return 'Must Be in E.164 Format (e.g. +353851046420)';
    }
}
