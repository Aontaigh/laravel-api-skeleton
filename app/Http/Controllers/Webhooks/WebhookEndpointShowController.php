<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Requests\Webhooks\WebhookEndpointShowRequest;
use App\Http\Resources\WebhookEndpointResource;
use App\Models\WebhookEndpoint;
use App\Queries\IndexFieldsQuery;
use App\Queries\Webhooks\WebhookEndpointQueryConstraints;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Returns a single webhook endpoint.
 *
 * @example
 * GET /api/webhook-endpoints/{webhook_endpoint}?fields[webhook_endpoints]=id,name,url
 */
final class WebhookEndpointShowController
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new WebhookEndpointShowController.
     *
     * @param IndexFieldsQuery $fieldsQuery composes sparse fieldsets onto any single-table builder
     */
    public function __construct(
        private readonly IndexFieldsQuery $fieldsQuery,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return the route-bound endpoint with optional sparse fieldsets.
     *
     * @param  WebhookEndpointShowRequest $request         the validated show request
     * @param  WebhookEndpoint            $webhookEndpoint the route-bound endpoint
     * @return JsonResponse               the standardised success envelope
     */
    public function __invoke(
        WebhookEndpointShowRequest $request,
        WebhookEndpoint $webhookEndpoint,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

        $endpointFields = $request->webhookEndpointFields();

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        */

        /** @var \Illuminate\Database\Eloquent\Builder<WebhookEndpoint> $query */
        $query = WebhookEndpoint::query()->whereKey($webhookEndpoint->getKey());

        $this->fieldsQuery->apply(
            query: $query,
            requestedFields: $endpointFields,
            allowedFields: WebhookEndpointQueryConstraints::ALLOWED_FIELDS,
            table: WebhookEndpointQueryConstraints::TABLE,
            requiredColumns: WebhookEndpointQueryConstraints::requiredSelectColumns(),
        );

        /** @var WebhookEndpoint $loadedEndpoint */
        $loadedEndpoint = $query->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: new WebhookEndpointResource($loadedEndpoint),
            message: 'Webhook Endpoint Retrieved Successfully',
        );
    }
}
