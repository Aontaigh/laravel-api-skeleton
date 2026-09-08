<?php

declare(strict_types=1);

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Canonical E.164 phone numbers for storage and API validation.
 *
 * Values must be a leading `+` followed by digits only (no spaces or punctuation).
 * libphonenumber parses the string, checks it is possible, and confirms the
 * input already matches {@see PhoneNumberFormat::E164} formatting.
 */
final class E164Phone
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the value is a canonical E.164 phone number.
     *
     * @example
     * E164Phone::isValid('+353851046420')
     *
     * @param  string|null $value the candidate phone number
     * @return bool        whether the value is valid canonical E.164
     */
    public static function isValid(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $input = trim($value);

        if ($input === '') {
            return false;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $phoneNumber = $util->parse($input, null);
        } catch (NumberParseException) {
            return false;
        }

        if (! $util->isPossibleNumber($phoneNumber)) {
            return false;
        }

        return $util->format($phoneNumber, PhoneNumberFormat::E164) === $input;
    }

    /**
     * Return the canonical E.164 form of a parseable phone number.
     *
     * Spaced or punctuated display forms (e.g. `+44 7700 900013`) compact to
     * canonical E.164 when libphonenumber considers them possible. Intended for
     * a future SMS two-factor channel, where client display forms must be
     * normalised before storage - the API validation rule itself stays strict
     * and only accepts canonical input.
     *
     * @example
     * E164Phone::normalize('+44 7700 900013')
     *
     * @param  string|null $value the candidate phone number
     * @return string|null the canonical E.164 value, or null when not parseable
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $input = trim($value);

        if ($input === '') {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $phoneNumber = $util->parse($input, null);
        } catch (NumberParseException) {
            return null;
        }

        if (! $util->isPossibleNumber($phoneNumber)) {
            return null;
        }

        return $util->format($phoneNumber, PhoneNumberFormat::E164);
    }
}
