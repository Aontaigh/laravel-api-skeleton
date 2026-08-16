<?php

declare(strict_types=1);

namespace App\Queries\Permissions;

use App\DataTransferObjects\Permissions\PermissionFilters;
use App\Queries\Roles\RoleQueryConstraints;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission;

/**
 * Applies Permission filters and guard scoping to an Eloquent builder.
 */
final class PermissionFilterQuery
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Compose filter and guard constraints onto the query.
     *
     * @param Builder<Permission> $query   the Permission query builder
     * @param PermissionFilters   $filters the validated filter DTO
     */
    public function apply(Builder $query, PermissionFilters $filters): void
    {
        $query->where(
            PermissionQueryConstraints::TABLE.'.guard_name',
            RoleQueryConstraints::GUARD_NAME,
        );

        if ($filters->search !== null) {
            $pattern = LikePattern::contains($filters->search);

            $query->whereRaw(
                LikePattern::containsWhereClause(PermissionQueryConstraints::TABLE.'.name'),
                [$pattern],
            );
        }
    }
}
