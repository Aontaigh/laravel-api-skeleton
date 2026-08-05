<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tokens;

use App\DataTransferObjects\Tokens\TokenFilters;
use App\Http\Requests\Tokens\TokenIndexRequest;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Queries\ListFieldsQuery;
use App\Queries\ListSortQuery;
use App\Queries\Tokens\TokenFilterQuery;
use App\Queries\Tokens\TokenQueryConstraints;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Returns a paginated, filterable list of the authenticated User's Tokens.
 *
 * @example
 * GET /api/tokens?filter[search]=cli&fields[tokens]=id,name&sort=-created_at&page=1&per_page=25
 */
final class TokenIndexController
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new Token Index Controller.
     *
     * @param TokenFilterQuery $filterQuery composes validated filters onto any Token builder
     * @param ListSortQuery    $sortQuery   composes validated sort onto any single-table builder
     * @param ListFieldsQuery  $fieldsQuery composes sparse fieldsets onto any single-table builder
     */
    public function __construct(
        private readonly TokenFilterQuery $filterQuery,
        private readonly ListSortQuery $sortQuery,
        private readonly ListFieldsQuery $fieldsQuery,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return a page of Tokens belonging to the authenticated User.
     *
     * @param  TokenIndexRequest $request the validated index request
     * @return JsonResponse      the standard API success envelope with pagination meta
     */
    public function __invoke(TokenIndexRequest $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

        $filters = new TokenFilters(
            search: $request->searchTerm(),
        );

        $sort = $request->listSort(
            TokenQueryConstraints::DEFAULT_SORT_COLUMN,
            TokenQueryConstraints::DEFAULT_SORT_DIRECTION,
        );
        $tokenFields = $request->tokenFields();
        $page = $request->safe()->integer('page', 1);
        $perPage = $request->safe()->integer('per_page', TokenQueryConstraints::DEFAULT_PER_PAGE);

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        */

        /** @var \Illuminate\Database\Eloquent\Builder<PersonalAccessToken> $query */
        $query = $request->viewer()->tokens()->getQuery();

        $this->filterQuery->apply($query, $filters);
        $this->sortQuery->apply(
            query: $query,
            sort: $sort,
            allowedSorts: TokenQueryConstraints::ALLOWED_SORTS,
            table: TokenQueryConstraints::TABLE,
        );

        $this->fieldsQuery->apply(
            query: $query,
            requestedFields: $tokenFields,
            allowedFields: TokenQueryConstraints::ALLOWED_FIELDS,
            table: TokenQueryConstraints::TABLE,
            requiredColumns: TokenQueryConstraints::requiredSelectColumns(),
        );

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: PersonalAccessTokenResource::collection($paginator->items()),
            message: 'Tokens Retrieved Successfully',
            meta: ['pagination' => ApiResponse::paginationMeta($paginator)],
        );
    }
}
