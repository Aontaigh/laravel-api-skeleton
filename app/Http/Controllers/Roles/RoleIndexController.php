<?php

declare(strict_types=1);

namespace App\Http\Controllers\Roles;

use App\DataTransferObjects\Roles\RoleFilters;
use App\Http\Requests\Roles\RoleIndexRequest;
use App\Http\Resources\RoleResource;
use App\Queries\ListFieldsQuery;
use App\Queries\ListSortQuery;
use App\Queries\Roles\RoleFilterQuery;
use App\Queries\Roles\RoleIncludeQuery;
use App\Queries\Roles\RoleQueryConstraints;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

/**
 * Returns a paginated, filterable list of Roles.
 *
 * @example
 * GET /api/roles?filter[search]=admin&fields[roles]=id,name&include=permissions&fields[permissions]=id,name&sort=name&page=1&per_page=25
 */
final class RoleIndexController
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new Role Index Controller.
     *
     * @param RoleFilterQuery  $filterQuery  composes validated filters onto any Role builder
     * @param ListSortQuery    $sortQuery    composes validated sort onto any single-table builder
     * @param ListFieldsQuery  $fieldsQuery  composes sparse fieldsets onto any single-table builder
     * @param RoleIncludeQuery $includeQuery composes validated includes onto any Role builder
     */
    public function __construct(
        private readonly RoleFilterQuery $filterQuery,
        private readonly ListSortQuery $sortQuery,
        private readonly ListFieldsQuery $fieldsQuery,
        private readonly RoleIncludeQuery $includeQuery,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return a page of Roles matching the active filters.
     *
     * @param  RoleIndexRequest $request the validated index request
     * @return JsonResponse     the standard API success envelope with pagination meta
     */
    public function __invoke(RoleIndexRequest $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

        $filters = new RoleFilters(
            search: $request->searchTerm(),
        );

        $sort = $request->listSort(
            RoleQueryConstraints::DEFAULT_SORT_COLUMN,
            RoleQueryConstraints::DEFAULT_SORT_DIRECTION,
        );
        $includes = $request->includes();
        $roleFields = $request->roleFields();
        $permissionFields = $request->permissionFields();
        $page = $request->safe()->integer('page', 1);
        $perPage = $request->safe()->integer('per_page', RoleQueryConstraints::DEFAULT_PER_PAGE);

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        */

        /** @var \Illuminate\Database\Eloquent\Builder<Role> $query */
        $query = Role::query();

        $this->filterQuery->apply($query, $filters);
        $this->sortQuery->apply(
            query: $query,
            sort: $sort,
            allowedSorts: RoleQueryConstraints::ALLOWED_SORTS,
            table: RoleQueryConstraints::TABLE,
        );

        $this->fieldsQuery->apply(
            query: $query,
            requestedFields: $roleFields,
            allowedFields: RoleQueryConstraints::ALLOWED_FIELDS,
            table: RoleQueryConstraints::TABLE,
            requiredColumns: RoleQueryConstraints::requiredSelectColumns($includes),
        );

        $this->includeQuery->apply($query, $includes, $permissionFields);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: RoleResource::collection($paginator->items()),
            message: 'Roles Retrieved Successfully',
            meta: ['pagination' => ApiResponse::paginationMeta($paginator)],
        );
    }
}
