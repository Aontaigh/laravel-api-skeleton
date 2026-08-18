<?php

declare(strict_types=1);

namespace App\Queries\Sessions;

use App\DataTransferObjects\Sessions\SessionFilters;
use App\Models\WebSession;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies Web Session filters and row scoping to an Eloquent builder.
 */
final class SessionFilterQuery
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Compose filter and row-scope constraints onto the query.
     *
     * @param Builder<WebSession> $query   the Web Session query builder
     * @param SessionFilters      $filters the validated filter DTO
     */
    public function apply(Builder $query, SessionFilters $filters): void
    {
        $query->whereNull(SessionQueryConstraints::TABLE.'.revoked_at');

        if (! $filters->listsAllUsers) {
            $query->where(
                SessionQueryConstraints::TABLE.'.user_id',
                $filters->viewer->id,
            );
        }

        if ($filters->userId !== null) {
            $query->where(
                SessionQueryConstraints::TABLE.'.user_id',
                $filters->userId,
            );
        }

        if ($filters->search !== null) {
            $pattern = LikePattern::contains($filters->search);

            $query->where(function (Builder $inner) use ($pattern): void {
                $inner
                    ->whereRaw(
                        LikePattern::containsWhereClause(SessionQueryConstraints::TABLE.'.device_name'),
                        [$pattern],
                    )
                    ->orWhereRaw(
                        LikePattern::containsWhereClause(SessionQueryConstraints::TABLE.'.ip_address'),
                        [$pattern],
                    )
                    ->orWhereRaw(
                        LikePattern::containsWhereClause(SessionQueryConstraints::TABLE.'.user_agent'),
                        [$pattern],
                    );
            });
        }
    }
}
