<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\E164Phone;

/**
 * Compacts phone fields to canonical E.164 before validation runs.
 *
 * Display forms clients actually submit (`+44 7700 900013`) are normalised to
 * the canonical compact form (`+447700900013`) in `prepareForValidation()`,
 * so the strict `E164PhoneNumber` rule sees canonical input. Values that do
 * not parse are left untouched for the rule to reject.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait NormalisesE164PhoneAttributes
{
    /*
    |--------------------------------------------------------------------------
    | Protected
    |--------------------------------------------------------------------------
    */

    /**
     * The request fields that should be compacted to canonical E.164.
     *
     * @return list<string> the attribute names to normalise
     */
    protected function e164PhoneAttributeKeys(): array
    {
        return [];
    }

    /**
     * Merge canonical E.164 values for any normalisable phone attributes.
     *
     * @param  list<string> $keys the attribute names to inspect
     * @return void
     */
    protected function normaliseE164PhoneAttributes(array $keys): void
    {
        $merged = [];

        foreach ($keys as $key) {
            if (! $this->has($key)) {
                continue;
            }

            $value = $this->input($key);

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $canonical = E164Phone::normalize($value);

            if ($canonical !== null) {
                $merged[$key] = $canonical;
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }
}
