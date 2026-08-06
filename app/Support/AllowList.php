<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Compares client-supplied values against a server allow-list.
 */
final class AllowList
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return requested values that are not on the allow-list.
     *
     * @param  array<int, string> $requested the client-supplied values
     * @param  list<string>       $allowed   the whitelisted values
     * @return list<string>       the unsupported values, preserving request order
     */
    public static function unsupported(array $requested, array $allowed): array
    {
        return array_values(array_diff($requested, $allowed));
    }

    /**
     * Return requested values that are on the allow-list.
     *
     * @param  array<int, string> $requested the client-supplied values
     * @param  list<string>       $allowed   the whitelisted values
     * @return list<string>       the supported values, preserving request order
     */
    public static function supported(array $requested, array $allowed): array
    {
        return array_values(array_intersect($requested, $allowed));
    }
}
