<?php

declare(strict_types=1);

namespace App\Queries\AuthAuditLogs;

use App\DataTransferObjects\AuthAuditLogs\AuthAuditLogFilters;
use App\Enums\AuthAuditEvent;
use App\Models\AuthAuditLog;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies auth audit log filters to an Eloquent builder.
 */
final class AuthAuditLogFilterQuery
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Compose filter constraints onto the query.
     *
     * @param  Builder<AuthAuditLog> $query   the audit log query builder
     * @param  AuthAuditLogFilters   $filters the validated filter DTO
     * @return void
     */
    public function apply(Builder $query, AuthAuditLogFilters $filters): void
    {
        if ($filters->search !== null) {
            $pattern = LikePattern::contains($filters->search);

            $query->whereRaw(
                LikePattern::containsWhereClause(AuthAuditLogQueryConstraints::TABLE.'.email'),
                [$pattern],
            );
        }

        if ($filters->events !== []) {
            $query->whereIn(
                AuthAuditLogQueryConstraints::TABLE.'.event',
                array_map(static fn (AuthAuditEvent $event): string => $event->value, $filters->events),
            );
        }

        if ($filters->userIds !== []) {
            $query->whereIn(AuthAuditLogQueryConstraints::TABLE.'.user_id', $filters->userIds);
        }

        if ($filters->apiClientIds !== []) {
            $query->whereIn(AuthAuditLogQueryConstraints::TABLE.'.api_client_id', $filters->apiClientIds);
        }
    }
}
