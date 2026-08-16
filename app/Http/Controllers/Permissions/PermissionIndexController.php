<?php

declare(strict_types=1);

namespace App\Http\Controllers\Permissions;

use App\DataTransferObjects\Permissions\PermissionFilters;
use App\Http\Requests\Permissions\PermissionIndexRequest;
use App\Http\Resources\PermissionResource;
use App\Queries\IndexFieldsQuery;
use App\Queries\IndexSortQuery;
use App\Queries\Permissions\PermissionFilterQuery;
use App\Queries\Permissions\PermissionQueryConstraints;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

/**
 * Returns a paginated list of registered Permission abilities.
 *
 * @example
 * GET /api/permissions?filter[search]=tokens&fields[permissions]=id,name&sort=name&page=1&per_page=25
 */
final class PermissionIndexController
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new Permission Index Controller.
     *
     * @param PermissionFilterQuery $filterQuery composes validated filters onto any Permission builder
     * @param IndexSortQuery        $sortQuery   composes validated sort onto any single-table builder
     * @param IndexFieldsQuery      $fieldsQuery composes sparse fieldsets onto any single-table builder
     */
    public function __construct(
        private readonly PermissionFilterQuery $filterQuery,
        private readonly IndexSortQuery $sortQuery,
        private readonly IndexFieldsQuery $fieldsQuery,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return a page of Permissions matching the active filters.
     *
     * @param  PermissionIndexRequest $request the validated index request
     * @return JsonResponse           the standard API success envelope with pagination meta
     */
    public function __invoke(PermissionIndexRequest $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

        $filters = new PermissionFilters(
            search: $request->searchTerm(),
        );

        $sort = $request->indexSort(
            PermissionQueryConstraints::DEFAULT_SORT_COLUMN,
            PermissionQueryConstraints::DEFAULT_SORT_DIRECTION,
        );
        $permissionFields = $request->permissionFields();
        $page = $request->safe()->integer('page', 1);
        $perPage = $request->safe()->integer('per_page', PermissionQueryConstraints::DEFAULT_PER_PAGE);

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        */

        /** @var \Illuminate\Database\Eloquent\Builder<Permission> $query */
        $query = Permission::query();

        $this->filterQuery->apply($query, $filters);
        $this->sortQuery->apply(
            query: $query,
            sort: $sort,
            allowedSorts: PermissionQueryConstraints::ALLOWED_SORTS,
            table: PermissionQueryConstraints::TABLE,
        );

        $this->fieldsQuery->apply(
            query: $query,
            requestedFields: $permissionFields,
            allowedFields: PermissionQueryConstraints::ALLOWED_FIELDS,
            table: PermissionQueryConstraints::TABLE,
            requiredColumns: PermissionQueryConstraints::requiredSelectColumns([]),
        );

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: PermissionResource::collection($paginator->items()),
            message: 'Permissions Retrieved Successfully',
            meta: ['pagination' => ApiResponse::paginationMeta($paginator)],
        );
    }
}
