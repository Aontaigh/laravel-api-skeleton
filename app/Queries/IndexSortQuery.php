<?php

declare(strict_types=1);

namespace App\Queries;

use App\DataTransferObjects\IndexSort;
use App\Support\QualifiedColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Applies whitelisted column sort order to any single-table Eloquent builder.
 *
 * Reuse across resources when sort is a straight `orderBy` on owned columns.
 * Add a resource-specific `{Resource}SortQuery` only when sort needs joins,
 * computed columns, or a non-`id` tie-break.
 */
final class IndexSortQuery
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Compose sort order onto the query.
     *
     * Appends a secondary tie-break on `{table}.{tieBreakColumn}` descending
     * when the primary sort column is not the tie-break column.
     *
     * The method is generic because `Builder`'s model template is
     * invariant: a plain `Builder<Model>` parameter makes every concrete
     * `Builder<User>` a caller actually holds a PHPStan error.
     *
     * @template TModel of Model
     *
     * @param Builder<TModel> $query          the Eloquent query builder
     * @param IndexSort       $sort           the validated sort DTO
     * @param list<string>    $allowedSorts   whitelisted column names
     * @param string          $table          table name or query alias
     * @param string          $tieBreakColumn stable secondary sort column (default `id`)
     */
    public function apply(
        Builder $query,
        IndexSort $sort,
        array $allowedSorts,
        string $table,
        string $tieBreakColumn = 'id',
    ): void {
        if (! in_array($sort->column, $allowedSorts, true)) {
            return;
        }

        $query->orderBy(QualifiedColumn::make($table, $sort->column), $sort->direction);

        if ($sort->column !== $tieBreakColumn) {
            $query->orderByDesc(QualifiedColumn::make($table, $tieBreakColumn));
        }
    }
}
