<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Builds `LIKE` patterns from untrusted search input.
 *
 * Binding a search term still leaves the `LIKE` wildcards `%` and `_` live
 * inside it: `filter[search]=%` would match every row, and a `_`-dense term
 * turns a scan into a CPU burn. Escape the term before wrapping it so the
 * user's characters are matched literally.
 */
final class LikePattern
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Build a "contains" pattern with the term's wildcards neutralised.
     *
     * @param  string $term the raw search term from the client
     * @return string the escaped pattern, wrapped in leading and trailing wildcards
     */
    public static function contains(string $term): string
    {
        return '%'.self::escape($term).'%';
    }

    /**
     * Build a `LIKE` comparison with an explicit escape character.
     *
     * PostgreSQL and SQLite do not treat backslash as an escape character
     * unless `ESCAPE` is declared. MySQL does by default, but
     * `NO_BACKSLASH_ESCAPES` disables that. Declaring `ESCAPE` keeps wildcard
     * neutralisation consistent across every driver this application runs
     * against.
     *
     * @param  literal-string $qualifiedColumn the table-qualified column name
     * @return literal-string the `column LIKE ? ESCAPE '\'` SQL fragment
     */
    public static function containsWhereClause(string $qualifiedColumn): string
    {
        return $qualifiedColumn." LIKE ? ESCAPE '\\\\'";
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Escape the `LIKE` wildcards and the escape character itself.
     *
     * The backslash is the escape character declared in
     * `containsWhereClause()`. Every caller that uses these patterns must
     * pair them with that `ESCAPE` clause or wildcard neutralisation
     * silently fails on PostgreSQL and SQLite.
     *
     * @param  string $term the raw search term
     * @return string the term with `\`, `%`, and `_` escaped
     */
    private static function escape(string $term): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $term,
        );
    }
}
