<?php

declare(strict_types=1);

namespace App\Http\Controllers\AuthAuditLogs;

use App\DataTransferObjects\AuthAuditLogs\AuthAuditLogFilters;
use App\Http\Requests\AuthAuditLogs\AuthAuditLogIndexRequest;
use App\Http\Resources\AuthAuditLogResource;
use App\Models\AuthAuditLog;
use App\Queries\AuthAuditLogs\AuthAuditLogFilterQuery;
use App\Queries\AuthAuditLogs\AuthAuditLogIncludeQuery;
use App\Queries\AuthAuditLogs\AuthAuditLogQueryConstraints;
use App\Queries\IndexFieldsQuery;
use App\Queries\IndexSortQuery;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Returns a paginated list of authentication audit log rows.
 *
 * @example
 * GET /api/audit-logs?filter[event]=Login Failed&sort=-created_at&include=user&page=1&per_page=25
 */
final class AuthAuditLogIndexController
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * @param AuthAuditLogFilterQuery  $filterQuery  composes validated filters onto any audit log builder
     * @param IndexSortQuery           $sortQuery    composes validated sort onto any single-table builder
     * @param IndexFieldsQuery         $fieldsQuery  composes sparse fieldsets onto any single-table builder
     * @param AuthAuditLogIncludeQuery $includeQuery composes validated includes onto any audit log builder
     */
    public function __construct(
        private readonly AuthAuditLogFilterQuery $filterQuery,
        private readonly IndexSortQuery $sortQuery,
        private readonly IndexFieldsQuery $fieldsQuery,
        private readonly AuthAuditLogIncludeQuery $includeQuery,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return a page of auth audit logs matching the active filters.
     */
    public function __invoke(AuthAuditLogIndexRequest $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

        $filters = new AuthAuditLogFilters(
            search: $request->searchTerm(),
            event: $request->eventFilter(),
            userId: $request->userIdFilter(),
            apiClientId: $request->apiClientIdFilter(),
        );

        $sort = $request->indexSort(
            AuthAuditLogQueryConstraints::DEFAULT_SORT_COLUMN,
            AuthAuditLogQueryConstraints::DEFAULT_SORT_DIRECTION,
        );
        $authAuditLogFields = $request->authAuditLogFields();
        $userFields = $request->auditLogUserFields();
        $includes = $request->includes();
        $page = $request->safe()->integer('page', 1);
        $perPage = $request->safe()->integer('per_page', AuthAuditLogQueryConstraints::DEFAULT_PER_PAGE);

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        */

        /** @var \Illuminate\Database\Eloquent\Builder<AuthAuditLog> $query */
        $query = AuthAuditLog::query();

        $this->filterQuery->apply($query, $filters);
        $this->sortQuery->apply(
            query: $query,
            sort: $sort,
            allowedSorts: AuthAuditLogQueryConstraints::ALLOWED_SORTS,
            table: AuthAuditLogQueryConstraints::TABLE,
        );

        $this->fieldsQuery->apply(
            query: $query,
            requestedFields: $authAuditLogFields,
            allowedFields: AuthAuditLogQueryConstraints::ALLOWED_FIELDS,
            table: AuthAuditLogQueryConstraints::TABLE,
            requiredColumns: AuthAuditLogQueryConstraints::requiredSelectColumns($includes),
        );

        $this->includeQuery->apply($query, $includes, $userFields, $request->allowedNestedUserFields());

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: AuthAuditLogResource::collection($paginator->items()),
            message: 'Auth Audit Logs Retrieved Successfully',
            meta: ['pagination' => ApiResponse::paginationMeta($paginator)],
        );
    }
}
