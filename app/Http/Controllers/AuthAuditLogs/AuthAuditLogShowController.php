<?php

declare(strict_types=1);

namespace App\Http\Controllers\AuthAuditLogs;

use App\Http\Requests\AuthAuditLogs\AuthAuditLogShowRequest;
use App\Http\Resources\AuthAuditLogResource;
use App\Models\AuthAuditLog;
use App\Queries\AuthAuditLogs\AuthAuditLogIncludeQuery;
use App\Queries\AuthAuditLogs\AuthAuditLogQueryConstraints;
use App\Queries\IndexFieldsQuery;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Returns a single authentication audit log row by ID.
 *
 * @example
 * GET /api/audit-logs/{auth_audit_log}?fields[auth_audit_logs]=id,event&include=user
 */
final class AuthAuditLogShowController
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * @param IndexFieldsQuery         $fieldsQuery  composes sparse fieldsets onto any single-table builder
     * @param AuthAuditLogIncludeQuery $includeQuery composes validated includes onto any audit log builder
     */
    public function __construct(
        private readonly IndexFieldsQuery $fieldsQuery,
        private readonly AuthAuditLogIncludeQuery $includeQuery,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return the route-bound audit log with optional sparse fieldsets and includes.
     */
    public function __invoke(
        AuthAuditLogShowRequest $request,
        AuthAuditLog $authAuditLog,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

        $includes = $request->includes();
        $authAuditLogFields = $request->authAuditLogFields();
        $userFields = $request->auditLogUserFields();

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        */

        /** @var \Illuminate\Database\Eloquent\Builder<AuthAuditLog> $query */
        $query = AuthAuditLog::query()->whereKey($authAuditLog->getKey());

        $this->fieldsQuery->apply(
            query: $query,
            requestedFields: $authAuditLogFields,
            allowedFields: AuthAuditLogQueryConstraints::ALLOWED_FIELDS,
            table: AuthAuditLogQueryConstraints::TABLE,
            requiredColumns: AuthAuditLogQueryConstraints::requiredSelectColumns($includes),
        );

        $this->includeQuery->apply($query, $includes, $userFields, $request->allowedNestedUserFields());

        /** @var AuthAuditLog $loadedLog */
        $loadedLog = $query->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: new AuthAuditLogResource($loadedLog),
            message: 'Auth Audit Log Retrieved Successfully',
        );
    }
}
