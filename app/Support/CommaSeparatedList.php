<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Parses comma-separated Query Params (`include`, `fields[…]`) into clean lists.
 *
 * Clients legitimately send `id, name` with spaces after the comma, so every
 * segment is trimmed before it reaches an allow-list comparison.
 */
final class CommaSeparatedList
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Split a comma-separated param into trimmed, non-empty values.
     *
     * @example
     * ```php
     * CommaSeparatedList::parse('id, name,,email'); // ['id', 'name', 'email']
     * ```
     *
     * @param  string       $value the raw query param value
     * @return list<string> the trimmed segments, preserving order
     */
    public static function parse(string $value): array
    {
        $segments = array_map(
            static fn (string $segment): string => trim($segment),
            explode(',', $value),
        );

        /*
         * Filter explicitly on the empty string: a bare `array_filter()` drops
         * the non-empty token `"0"`, which would turn an explicit value into an
         * omitted one.
         */
        return array_values(array_filter(
            $segments,
            static fn (string $segment): bool => $segment !== '',
        ));
    }
}
