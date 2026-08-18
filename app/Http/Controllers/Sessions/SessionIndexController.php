<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sessions;

use App\DataTransferObjects\Sessions\SessionFilters;
use App\Http\Requests\Sessions\SessionIndexRequest;
use App\Http\Resources\WebSessionResource;
use App\Models\WebSession;
use App\Queries\IndexFieldsQuery;
use App\Queries\IndexSortQuery;
use App\Queries\Sessions\SessionFilterQuery;
use App\Queries\Sessions\SessionIncludeQuery;
use App\Queries\Sessions\SessionQueryConstraints;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Returns a paginated list of registered cookie-bound web sessions.
 *
 * @example
 * GET /api/sessions?filter[search]=chrome&sort=-last_activity_at&include=user&page=1&per_page=25
 */
final class SessionIndexController
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * @param SessionFilterQuery  $filterQuery  composes validated filters onto any Web Session builder
     * @param IndexSortQuery      $sortQuery    composes validated sort onto any single-table builder
     * @param IndexFieldsQuery    $fieldsQuery  composes sparse fieldsets onto any single-table builder
     * @param SessionIncludeQuery $includeQuery composes validated includes onto any Web Session builder
     */
    public function __construct(
        private readonly SessionFilterQuery $filterQuery,
        private readonly IndexSortQuery $sortQuery,
        private readonly IndexFieldsQuery $fieldsQuery,
        private readonly SessionIncludeQuery $includeQuery,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return a page of web sessions matching the active filters.
     *
     * @param  SessionIndexRequest $request the validated index request
     * @return JsonResponse        the standard API success envelope with pagination meta
     */
    public function __invoke(SessionIndexRequest $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

        $filters = new SessionFilters(
            viewer: $request->viewer(),
            listsAllUsers: $request->listsAllUsers(),
            search: $request->searchTerm(),
            userId: $request->userIdFilter(),
        );

        $sort = $request->indexSort(
            SessionQueryConstraints::DEFAULT_SORT_COLUMN,
            SessionQueryConstraints::DEFAULT_SORT_DIRECTION,
        );
        $sessionFields = $request->sessionFields();
        $userFields = $request->sessionUserFields();
        $includes = $request->includes();
        $page = $request->safe()->integer('page', 1);
        $perPage = $request->safe()->integer('per_page', SessionQueryConstraints::DEFAULT_PER_PAGE);

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        */

        $query = WebSession::query();

        $this->filterQuery->apply($query, $filters);
        $this->sortQuery->apply(
            query: $query,
            sort: $sort,
            allowedSorts: SessionQueryConstraints::ALLOWED_SORTS,
            table: SessionQueryConstraints::TABLE,
        );

        $this->fieldsQuery->apply(
            query: $query,
            requestedFields: $sessionFields,
            allowedFields: $request->allowedSessionDatabaseFields(),
            table: SessionQueryConstraints::TABLE,
            requiredColumns: SessionQueryConstraints::requiredSelectColumns(),
        );

        $this->includeQuery->apply(
            query: $query,
            includes: $includes,
            userFields: $userFields,
        );

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: WebSessionResource::collection($paginator->items()),
            message: 'Sessions Retrieved Successfully',
            meta: ['pagination' => ApiResponse::paginationMeta($paginator)],
        );
    }
}
