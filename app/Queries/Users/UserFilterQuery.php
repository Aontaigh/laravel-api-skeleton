<?php

declare(strict_types=1);

namespace App\Queries\Users;

use App\DataTransferObjects\Users\UserFilters;
use App\Models\User;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies User filters and row scoping to an Eloquent builder.
 */
final class UserFilterQuery
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Compose filter and row-scope constraints onto the query.
     *
     * @param Builder<User> $query   the User query builder
     * @param UserFilters   $filters the validated filter DTO
     */
    public function apply(Builder $query, UserFilters $filters): void
    {
        if (! $filters->listsAllTeams) {
            $query->where('users.team_id', $filters->viewer->team_id);
        }

        if ($filters->search !== null) {
            $pattern = LikePattern::contains($filters->search);

            $query->where(function (Builder $inner) use ($pattern): void {
                $inner
                    ->whereRaw(LikePattern::containsWhereClause('users.name'), [$pattern])
                    ->orWhereRaw(LikePattern::containsWhereClause('users.email'), [$pattern]);
            });
        }
    }
}
