<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\DataTransferObjects\Webhooks\WebhookDeliveryFilters;
use App\Http\Requests\Webhooks\WebhookDeliveryIndexRequest;
use App\Http\Resources\WebhookDeliveryResource;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Queries\IndexFieldsQuery;
use App\Queries\IndexSortQuery;
use App\Queries\Webhooks\WebhookDeliveryFilterQuery;
use App\Queries\Webhooks\WebhookDeliveryQueryConstraints;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Returns a paginated list of delivery attempts for one endpoint.
 *
 * @example
 * GET /api/webhook-endpoints/{webhook_endpoint}/deliveries?filter[status]=failed&sort=-id&page=1&per_page=25
 */
final class WebhookDeliveryIndexController
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new WebhookDeliveryIndexController.
     *
     * @param WebhookDeliveryFilterQuery $filterQuery composes validated filters onto any delivery builder
     * @param IndexSortQuery             $sortQuery   composes validated sort onto any single-table builder
     * @param IndexFieldsQuery           $fieldsQuery composes sparse fieldsets onto any single-table builder
     */
    public function __construct(
        private readonly WebhookDeliveryFilterQuery $filterQuery,
        private readonly IndexSortQuery $sortQuery,
        private readonly IndexFieldsQuery $fieldsQuery,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return a page of deliveries for the route-bound endpoint.
     *
     * @param  WebhookDeliveryIndexRequest $request         the validated index request
     * @param  WebhookEndpoint             $webhookEndpoint the endpoint scoping the index (route-bound)
     * @return JsonResponse                the standard API success envelope with pagination meta
     */
    public function __invoke(
        WebhookDeliveryIndexRequest $request,
        WebhookEndpoint $webhookEndpoint,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        $filters = new WebhookDeliveryFilters(
            event: $input->has('filter.event') ? $input->string('filter.event')->toString() : null,
            status: $input->has('filter.status') ? $input->string('filter.status')->toString() : null,
        );

        $sort = $request->indexSort(
            WebhookDeliveryQueryConstraints::DEFAULT_SORT_COLUMN,
            WebhookDeliveryQueryConstraints::DEFAULT_SORT_DIRECTION,
        );
        $deliveryFields = $request->webhookDeliveryFields();
        $page = $input->integer('page', 1);
        $perPage = $input->integer('per_page', WebhookDeliveryQueryConstraints::DEFAULT_PER_PAGE);

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        */

        /** @var \Illuminate\Database\Eloquent\Builder<WebhookDelivery> $query */
        $query = WebhookDelivery::query();

        $this->filterQuery->apply($query, $webhookEndpoint, $filters);
        $this->sortQuery->apply(
            query: $query,
            sort: $sort,
            allowedSorts: WebhookDeliveryQueryConstraints::ALLOWED_SORTS,
            table: WebhookDeliveryQueryConstraints::TABLE,
        );

        $this->fieldsQuery->apply(
            query: $query,
            requestedFields: $deliveryFields,
            allowedFields: WebhookDeliveryQueryConstraints::ALLOWED_FIELDS,
            table: WebhookDeliveryQueryConstraints::TABLE,
            requiredColumns: WebhookDeliveryQueryConstraints::requiredSelectColumns(),
        );

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: WebhookDeliveryResource::collection($paginator->items()),
            message: 'Webhook Deliveries Retrieved Successfully',
            meta: ['pagination' => ApiResponse::paginationMeta($paginator)],
        );
    }
}
