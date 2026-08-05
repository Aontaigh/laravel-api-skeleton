<?php

declare(strict_types=1);

namespace App\Queries\Roles;

use App\DataTransferObjects\Roles\RoleFilters;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

/**
 * Applies Role filters and guard scoping to an Eloquent builder.
 */
final class RoleFilterQuery
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Compose filter and guard constraints onto the query.
     *
     * @param Builder<Role> $query   the Role query builder
     * @param RoleFilters   $filters the validated filter DTO
     */
    public function apply(Builder $query, RoleFilters $filters): void
    {
        $query->where(
            RoleQueryConstraints::TABLE.'.guard_name',
            RoleQueryConstraints::GUARD_NAME,
        );

        if ($filters->search !== null) {
            $pattern = LikePattern::contains($filters->search);

            $query->whereRaw(
                LikePattern::containsWhereClause(RoleQueryConstraints::TABLE.'.name'),
                [$pattern],
            );
        }
    }
}
