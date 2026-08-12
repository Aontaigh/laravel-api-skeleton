<?php

declare(strict_types=1);

namespace App\Queries;

use App\Support\QualifiedColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves whitelisted sparse fieldsets onto an Eloquent builder.
 *
 * Reuse across resources for straight `select()` on a single table. Merge
 * client-requested columns with required columns (the key, and foreign keys
 * needed by eager loads).
 */
final class IndexFieldsQuery
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Apply a sparse fieldset to the query, or the default allow-listed projection.
     *
     * When `$requestedFields` is null the query selects every column in
     * `$allowedFields` plus `$requiredColumns` — never `SELECT *`. Callers
     * must keep secrets (password hashes, token digests) out of `$allowedFields`.
     *
     * `$requiredColumns` bypasses `$allowedFields` so internal columns
     * (foreign keys) can be selected without exposing them to `fields[…]`.
     * It must come from constants, never from the request — see
     * `QualifiedColumn`.
     *
     * The method is generic because `Builder`'s model template is
     * invariant: a plain `Builder<Model>` parameter makes every concrete
     * `Builder<User>` a caller actually holds a PHPStan error.
     *
     * @template TModel of Model
     *
     * @param Builder<TModel>   $query           the Eloquent query builder
     * @param list<string>|null $requestedFields client sparse fieldset, or null for all columns
     * @param list<string>      $allowedFields   whitelisted API column names
     * @param string            $table           table name or query alias
     * @param list<string>      $requiredColumns columns always selected (never client-controlled)
     */
    public function apply(
        Builder $query,
        ?array $requestedFields,
        array $allowedFields,
        string $table,
        array $requiredColumns = ['id'],
    ): void {
        $columns = $requestedFields === null
            ? array_values(array_unique(array_merge($requiredColumns, $allowedFields)))
            : array_values(array_unique(array_merge(
                $requiredColumns,
                array_intersect($requestedFields, $allowedFields),
            )));

        $query->select(array_map(
            static fn (string $column): string => QualifiedColumn::make($table, $column),
            $columns,
        ));
    }
}
