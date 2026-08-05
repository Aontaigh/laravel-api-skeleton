<?php

declare(strict_types=1);

namespace App\Queries\Tokens;

use App\DataTransferObjects\Tokens\TokenFilters;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Applies Token filters to an Eloquent builder.
 *
 * Row scoping (only the viewer's own Tokens) is applied by the controller
 * before this class runs — filters here are purely query refinements.
 */
final class TokenFilterQuery
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Compose filter constraints onto the query.
     *
     * @param Builder<PersonalAccessToken> $query   the Token query builder
     * @param TokenFilters                 $filters the validated filter DTO
     */
    public function apply(Builder $query, TokenFilters $filters): void
    {
        if ($filters->search !== null) {
            $pattern = LikePattern::contains($filters->search);

            $query->whereRaw(
                LikePattern::containsWhereClause(TokenQueryConstraints::TABLE.'.name'),
                [$pattern],
            );
        }
    }
}
