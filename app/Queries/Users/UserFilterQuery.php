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
     * @param  Builder<User> $query   the User query builder
     * @param  UserFilters   $filters the validated filter DTO
     * @return void
     */
    public function apply(Builder $query, UserFilters $filters): void
    {
        /*
         * The directory lists people, not machine identities: API Client
         * backing accounts carry `is_service_account` and are managed through
         * `/api/clients`, so they never answer a Users listing even when the
         * viewer holds `users.list-all` and is not Team-scoped below.
         */
        $query->where('users.is_service_account', false);

        if (! $filters->listsAllTeams) {
            $query->where('users.team_id', $filters->viewer->team_id);
        }

        if ($filters->search !== null) {
            $pattern = LikePattern::contains($filters->search);

            /*
             * The email arm only applies to viewers holding `users.view-email`:
             * a search term matching an email would otherwise confirm that
             * address exists for viewers who may not display it.
             */
            $query->where(function (Builder $inner) use ($pattern, $filters): void {
                $inner->whereRaw(LikePattern::containsWhereClause('users.name'), [$pattern]);

                if ($filters->canViewEmails) {
                    $inner->orWhereRaw(LikePattern::containsWhereClause('users.email'), [$pattern]);
                }
            });
        }

        /*
         * The three statuses are mutually exclusive, so a list is a plain OR of the same
         * branches the scalar filter used, and a single value takes the identical path as
         * before rather than a special case. `deleted` needs `withTrashed` because the default
         * scope hides soft-deleted rows - that scope is why reaching a deleted User at all
         * (so it can be restored) requires opting out of it.
         */
        if ($filters->statuses !== []) {
            $this->applyStatusFilter($query, $filters->statuses);
        }

        if ($filters->roles !== []) {
            $query->whereHas('roles', function (Builder $roles) use ($filters): void {
                $roles->whereIn('name', $filters->roles);
            });
        }

        if ($filters->from !== null) {
            $query->where('users.created_at', '>=', $filters->from);
        }

        if ($filters->to !== null) {
            $query->where('users.created_at', '<=', $filters->to);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Constrain the query to Users in any of the requested account statuses.
     *
     * @param  Builder<User> $query    the User query builder
     * @param  list<string>  $statuses the validated statuses to match
     * @return void
     */
    private function applyStatusFilter(Builder $query, array $statuses): void
    {
        if (in_array('deleted', $statuses, true)) {
            $query->withTrashed();
        }

        $query->where(function (Builder $scoped) use ($statuses): void {
            foreach ($statuses as $status) {
                $scoped->orWhere(function (Builder $branch) use ($status): void {
                    match ($status) {
                        'active' => $branch->whereNull('users.suspended_at')->whereNull('users.deleted_at'),
                        'suspended' => $branch->whereNotNull('users.suspended_at')->whereNull('users.deleted_at'),
                        'deleted' => $branch->whereNotNull('users.deleted_at'),
                        default => null,
                    };
                });
            }
        });
    }
}
