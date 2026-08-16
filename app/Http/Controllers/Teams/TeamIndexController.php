<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teams;

use App\DataTransferObjects\Teams\TeamFilters;
use App\Http\Requests\Teams\TeamIndexRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Queries\IndexFieldsQuery;
use App\Queries\IndexSortQuery;
use App\Queries\Teams\TeamFilterQuery;
use App\Queries\Teams\TeamQueryConstraints;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Returns a paginated, filterable list of Teams.
 *
 * @example
 * GET /api/teams?filter[search]=engineering&fields[teams]=id,name&sort=name&page=1&per_page=25
 */
final class TeamIndexController
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * @param TeamFilterQuery  $filterQuery composes validated filters onto any Team builder
     * @param IndexSortQuery   $sortQuery   composes validated sort onto any single-table builder
     * @param IndexFieldsQuery $fieldsQuery composes sparse fieldsets onto any single-table builder
     */
    public function __construct(
        private readonly TeamFilterQuery $filterQuery,
        private readonly IndexSortQuery $sortQuery,
        private readonly IndexFieldsQuery $fieldsQuery,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return a page of Teams matching the active filters.
     *
     * @param  TeamIndexRequest $request the validated index request
     * @return JsonResponse     the standard API success envelope with pagination meta
     */
    public function __invoke(TeamIndexRequest $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

        $filters = new TeamFilters(
            search: $request->searchTerm(),
        );

        $sort = $request->indexSort(
            TeamQueryConstraints::DEFAULT_SORT_COLUMN,
            TeamQueryConstraints::DEFAULT_SORT_DIRECTION,
        );
        $teamFields = $request->teamFields();
        $page = $request->safe()->integer('page', 1);
        $perPage = $request->safe()->integer('per_page', TeamQueryConstraints::DEFAULT_PER_PAGE);

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        */

        /** @var \Illuminate\Database\Eloquent\Builder<Team> $query */
        $query = Team::query();

        $this->filterQuery->apply($query, $filters);
        $this->sortQuery->apply(
            query: $query,
            sort: $sort,
            allowedSorts: TeamQueryConstraints::ALLOWED_SORTS,
            table: TeamQueryConstraints::TABLE,
        );

        $this->fieldsQuery->apply(
            query: $query,
            requestedFields: $teamFields,
            allowedFields: TeamQueryConstraints::ALLOWED_FIELDS,
            table: TeamQueryConstraints::TABLE,
            requiredColumns: TeamQueryConstraints::requiredSelectColumns(),
        );

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: TeamResource::collection($paginator->items()),
            message: 'Teams Retrieved Successfully',
            meta: ['pagination' => ApiResponse::paginationMeta($paginator)],
        );
    }
}
