<?php

declare(strict_types=1);

namespace App\Queries\Teams;

use App\DataTransferObjects\Teams\TeamFilters;
use App\Models\Team;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;

/**
 * Composes validated filters onto any Team query builder.
 */
final class TeamFilterQuery
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Apply filter constraints to the query.
     *
     * @param Builder<Team> $query   the Team query builder
     * @param TeamFilters   $filters the validated filter DTO
     */
    public function apply(Builder $query, TeamFilters $filters): void
    {
        if ($filters->search !== null) {
            $pattern = LikePattern::contains($filters->search);

            $query->whereRaw(
                LikePattern::containsWhereClause(TeamQueryConstraints::TABLE.'.name'),
                [$pattern],
            );
        }
    }
}
