<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clients;

use App\DataTransferObjects\ApiClients\ApiClientFilters;
use App\Http\Requests\ApiClients\ClientIndexRequest;
use App\Http\Resources\ApiClientResource;
use App\Models\ApiClient;
use App\Queries\ApiClients\ApiClientFilterQuery;
use App\Queries\ApiClients\ApiClientQueryConstraints;
use App\Queries\IndexFieldsQuery;
use App\Queries\IndexSortQuery;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Returns a paginated list of API clients.
 *
 * @example
 * GET /api/clients?filter[search]=billing&sort=-created_at&page=1&per_page=25
 */
final class ClientIndexController
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new Client Index Controller.
     *
     * @param ApiClientFilterQuery $filterQuery composes validated filters onto any ApiClient builder
     * @param IndexSortQuery       $sortQuery   composes validated sort onto any single-table builder
     * @param IndexFieldsQuery     $fieldsQuery composes sparse fieldsets onto any single-table builder
     */
    public function __construct(
        private readonly ApiClientFilterQuery $filterQuery,
        private readonly IndexSortQuery $sortQuery,
        private readonly IndexFieldsQuery $fieldsQuery,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return a page of API clients matching the active filters.
     *
     * @param  ClientIndexRequest $request the validated index request
     * @return JsonResponse       the standard API success envelope with pagination meta
     */
    public function __invoke(ClientIndexRequest $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

        $filters = new ApiClientFilters(
            search: $request->searchTerm(),
        );

        $sort = $request->indexSort(
            ApiClientQueryConstraints::DEFAULT_SORT_COLUMN,
            ApiClientQueryConstraints::DEFAULT_SORT_DIRECTION,
        );
        $clientFields = $request->apiClientFields();
        $page = $request->safe()->integer('page', 1);
        $perPage = $request->safe()->integer('per_page', ApiClientQueryConstraints::DEFAULT_PER_PAGE);

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        */

        /** @var \Illuminate\Database\Eloquent\Builder<ApiClient> $query */
        $query = ApiClient::query();

        $this->filterQuery->apply($query, $filters);
        $this->sortQuery->apply(
            query: $query,
            sort: $sort,
            allowedSorts: ApiClientQueryConstraints::ALLOWED_SORTS,
            table: ApiClientQueryConstraints::TABLE,
        );

        $this->fieldsQuery->apply(
            query: $query,
            requestedFields: $clientFields,
            allowedFields: ApiClientQueryConstraints::ALLOWED_FIELDS,
            table: ApiClientQueryConstraints::TABLE,
            requiredColumns: ApiClientQueryConstraints::requiredSelectColumns([]),
        );

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: ApiClientResource::collection($paginator->items()),
            message: 'API Clients Retrieved Successfully',
            meta: ['pagination' => ApiResponse::paginationMeta($paginator)],
        );
    }
}
