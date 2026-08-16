<?php

declare(strict_types=1);

namespace App\Queries\AuthAuditLogs;

use App\DataTransferObjects\AuthAuditLogs\AuthAuditLogFilters;
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
     * @param Builder<AuthAuditLog> $query   the audit log query builder
     * @param AuthAuditLogFilters   $filters the validated filter DTO
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

        if ($filters->event !== null) {
            $query->where(
                AuthAuditLogQueryConstraints::TABLE.'.event',
                $filters->event->value,
            );
        }

        if ($filters->userId !== null) {
            $query->where(
                AuthAuditLogQueryConstraints::TABLE.'.user_id',
                $filters->userId,
            );
        }

        if ($filters->apiClientId !== null) {
            $query->where(
                AuthAuditLogQueryConstraints::TABLE.'.api_client_id',
                $filters->apiClientId,
            );
        }
    }
}
