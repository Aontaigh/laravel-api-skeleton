<?php

declare(strict_types=1);

namespace App\Queries\Tokens;

use App\DataTransferObjects\Tokens\TokenFilters;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Applies Token filters and row scoping to an Eloquent builder.
 */
final class TokenFilterQuery
{
    /*
    |--------------------------------------------------------------------------​
    | Public
    |--------------------------------------------------------------------------​
    */

    /**
     * Compose filter and row-scope constraints onto the query.
     *
     * Row scoping constrains tokens to those owned by the viewer.
     *
     * @param Builder<PersonalAccessToken> $query   the Token query builder
     * @param TokenFilters                 $filters the validated filter DTO
     */
    public function apply(Builder $query, TokenFilters $filters): void
    {
        $query->where(
            TokenQueryConstraints::TABLE.'.tokenable_id',
            $filters->viewer->id,
        );

        if ($filters->search !== null) {
            $pattern = LikePattern::contains($filters->search);

            $query->whereRaw(
                LikePattern::containsWhereClause(TokenQueryConstraints::TABLE.'.name'),
                [$pattern],
            );
        }
    }
}
