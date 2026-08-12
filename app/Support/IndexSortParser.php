<?php

declare(strict_types=1);

namespace App\Support;

use App\DataTransferObjects\IndexSort;

/**
 * Parses the `sort` query param into a normalised column and direction.
 */
final class IndexSortParser
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Split a raw `sort` value into its column and direction.
     *
     * Strips exactly one leading `-` so a malformed `--name` fails the
     * allow-list instead of silently resolving to `name`.
     *
     * @param  string    $raw the raw `sort` query param
     * @return IndexSort the parsed sort, with an empty column when `sort` is blank
     */
    public static function parse(string $raw): IndexSort
    {
        $trimmed = trim($raw);
        $isDescending = str_starts_with($trimmed, '-');

        return new IndexSort(
            column: $isDescending ? substr($trimmed, 1) : $trimmed,
            direction: $isDescending ? 'desc' : 'asc',
        );
    }
}
