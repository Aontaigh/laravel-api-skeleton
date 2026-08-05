<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Serialises dates for API responses in a single consistent format.
 */
final class ApiDateTime
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Serialise a datetime to ISO 8601 UTC.
     *
     * Copies before shifting the timezone: `Illuminate\Support\Carbon` is
     * mutable, so calling `utc()` on the Model's own instance would rewrite
     * the attribute in memory as a side effect of rendering it.
     *
     * @param  CarbonInterface|null $value the datetime to serialise
     * @return string|null          the ISO 8601 string, or null when the value is null
     */
    public static function serialize(?CarbonInterface $value): ?string
    {
        return $value?->copy()->utc()->toIso8601String();
    }
}
