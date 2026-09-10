<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\DataTransferObjects\Webhooks\WebhookEndpointFilters;
use App\Http\Requests\Webhooks\WebhookEndpointIndexRequest;
use App\Http\Resources\WebhookEndpointResource;
use App\Models\WebhookEndpoint;
use App\Queries\IndexFieldsQuery;
use App\Queries\IndexSortQuery;
use App\Queries\Webhooks\WebhookEndpointFilterQuery;
use App\Queries\Webhooks\WebhookEndpointQueryConstraints;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Returns a paginated, filterable list of webhook endpoints.
 *
 * @example
 * GET /api/webhook-endpoints?filter[search]=billing&fields[webhook_endpoints]=id,name,url&sort=name&page=1&per_page=25
 */
final class WebhookEndpointIndexController
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new WebhookEndpointIndexController.
     *
     * @param WebhookEndpointFilterQuery $filterQuery composes validated filters onto any endpoint builder
     * @param IndexSortQuery             $sortQuery   composes validated sort onto any single-table builder
     * @param IndexFieldsQuery           $fieldsQuery composes sparse fieldsets onto any single-table builder
     */
    public function __construct(
        private readonly WebhookEndpointFilterQuery $filterQuery,
        private readonly IndexSortQuery $sortQuery,
        private readonly IndexFieldsQuery $fieldsQuery,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return a page of webhook endpoints matching the active filters.
     *
     * @param  WebhookEndpointIndexRequest $request the validated index request
     * @return JsonResponse                the standard API success envelope with pagination meta
     */
    public function __invoke(WebhookEndpointIndexRequest $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

        $filters = new WebhookEndpointFilters(
            search: $request->searchTerm(),
        );

        $sort = $request->indexSort(
            WebhookEndpointQueryConstraints::DEFAULT_SORT_COLUMN,
            WebhookEndpointQueryConstraints::DEFAULT_SORT_DIRECTION,
        );
        $endpointFields = $request->webhookEndpointFields();
        $page = $request->safe()->integer('page', 1);
        $perPage = $request->safe()->integer('per_page', WebhookEndpointQueryConstraints::DEFAULT_PER_PAGE);

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        */

        /** @var \Illuminate\Database\Eloquent\Builder<WebhookEndpoint> $query */
        $query = WebhookEndpoint::query();

        $this->filterQuery->apply($query, $filters);
        $this->sortQuery->apply(
            query: $query,
            sort: $sort,
            allowedSorts: WebhookEndpointQueryConstraints::ALLOWED_SORTS,
            table: WebhookEndpointQueryConstraints::TABLE,
        );

        $this->fieldsQuery->apply(
            query: $query,
            requestedFields: $endpointFields,
            allowedFields: WebhookEndpointQueryConstraints::ALLOWED_FIELDS,
            table: WebhookEndpointQueryConstraints::TABLE,
            requiredColumns: WebhookEndpointQueryConstraints::requiredSelectColumns(),
        );

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: WebhookEndpointResource::collection($paginator->items()),
            message: 'Webhook Endpoints Retrieved Successfully',
            meta: ['pagination' => ApiResponse::paginationMeta($paginator)],
        );
    }
}
