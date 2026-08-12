<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\DataTransferObjects\Users\UserFilters;
use App\Http\Requests\Users\UserIndexRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Queries\IndexFieldsQuery;
use App\Queries\IndexSortQuery;
use App\Queries\Users\UserFilterQuery;
use App\Queries\Users\UserIncludeQuery;
use App\Queries\Users\UserQueryConstraints;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Returns a paginated, filterable list of Users.
 *
 * @example
 * GET /api/users?filter[search]=acme&fields[users]=id,name&include=team,role&sort=-created_at&page=1&per_page=25
 */
final class UserIndexController
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new User Index Controller.
     *
     * @param UserFilterQuery  $filterQuery  composes validated filters onto any User builder
     * @param IndexSortQuery   $sortQuery    composes validated sort onto any single-table builder
     * @param IndexFieldsQuery $fieldsQuery  composes sparse fieldsets onto any single-table builder
     * @param UserIncludeQuery $includeQuery composes validated includes onto any User builder
     */
    public function __construct(
        private readonly UserFilterQuery $filterQuery,
        private readonly IndexSortQuery $sortQuery,
        private readonly IndexFieldsQuery $fieldsQuery,
        private readonly UserIncludeQuery $includeQuery,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return a page of Users matching the active filters.
     *
     * @param  UserIndexRequest $request the validated index request
     * @return JsonResponse     the standard API success envelope with pagination meta
     */
    public function __invoke(UserIndexRequest $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

        $filters = new UserFilters(
            viewer: $request->viewer(),
            listsAllTeams: $request->listsAllTeams(),
            search: $request->searchTerm(),
        );

        $sort = $request->indexSort(
            UserQueryConstraints::DEFAULT_SORT_COLUMN,
            UserQueryConstraints::DEFAULT_SORT_DIRECTION,
        );
        $includes = $request->includes();
        $userFields = $request->userFields();
        $teamFields = $request->teamFields();
        $roleFields = $request->roleFields();
        $page = $request->safe()->integer('page', 1);
        $perPage = $request->safe()->integer('per_page', UserQueryConstraints::DEFAULT_PER_PAGE);

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        */

        $query = User::query();

        $this->filterQuery->apply($query, $filters);
        $this->sortQuery->apply(
            query: $query,
            sort: $sort,
            allowedSorts: UserQueryConstraints::ALLOWED_SORTS,
            table: 'users',
        );

        $this->fieldsQuery->apply(
            query: $query,
            requestedFields: $userFields,
            allowedFields: $request->allowedUserFields(),
            table: 'users',
            requiredColumns: UserQueryConstraints::requiredSelectColumns($includes),
        );

        $this->includeQuery->apply($query, $includes, $teamFields, $roleFields);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: UserResource::collection($paginator->items()),
            message: 'Users Retrieved Successfully',
            meta: ['pagination' => ApiResponse::paginationMeta($paginator)],
        );
    }
}
