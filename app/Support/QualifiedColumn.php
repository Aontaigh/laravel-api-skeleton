<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Builds `table.column` references for query builders.
 *
 * Column and table names cannot be bound as parameters, so they are the one
 * part of a query that is still string-interpolated. Callers are expected to
 * allow-list them first; this is the last-mile guard that turns a slip in
 * that allow-listing into an exception instead of injected SQL.
 */
final class QualifiedColumn
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Qualify a column with its table, rejecting anything that is not a bare identifier.
     *
     * @example
     * ```php
     * QualifiedColumn::make('users', 'name'); // 'users.name'
     * ```
     *
     * @param  string $table  the table name or query alias
     * @param  string $column the unqualified column name
     * @return string the qualified `table.column` reference
     *
     * @throws InvalidArgumentException when either identifier is not a bare identifier
     */
    public static function make(string $table, string $column): string
    {
        return self::guard($table).'.'.self::guard($column);
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Assert that an identifier is a bare `[A-Za-z_][A-Za-z0-9_]*` name.
     *
     * @param  string $identifier the table or column name to check
     * @return string the identifier, unchanged
     *
     * @throws InvalidArgumentException when the identifier could carry SQL
     */
    private static function guard(string $identifier): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidArgumentException("Unsafe Database Identifier: {$identifier}");
        }

        return $identifier;
    }
}
