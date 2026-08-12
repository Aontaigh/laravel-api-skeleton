<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalises the `filter[search]` query param into a trimmed string or null.
 */
final class SearchTermParser
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Trim a raw search term and return null when blank.
     *
     * @param  string      $raw the raw `filter[search]` value
     * @return string|null the trimmed term, or null when empty after trimming
     */
    public static function normalize(string $raw): ?string
    {
        $trimmed = trim($raw);

        return $trimmed === '' ? null : $trimmed;
    }
}
