<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\Webhooks\PingWebhookEndpointAction;
use App\Http\Requests\Webhooks\TestWebhookEndpointRequest;
use App\Http\Resources\WebhookDeliveryResource;
use App\Models\WebhookEndpoint;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Queues a synthetic ping delivery so integrators can verify their receiver.
 *
 * @example
 * POST /api/webhook-endpoints/{webhook_endpoint}/test
 */
final class TestWebhookEndpointController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Queue a `webhook.ping` delivery for the given endpoint.
     *
     * @param  TestWebhookEndpointRequest $request         the validated test request
     * @param  WebhookEndpoint            $webhookEndpoint the endpoint being pinged (route-bound)
     * @param  PingWebhookEndpointAction  $action          the test-ping Action
     * @return JsonResponse               the standardised success envelope
     */
    public function __invoke(
        TestWebhookEndpointRequest $request,
        WebhookEndpoint $webhookEndpoint,
        PingWebhookEndpointAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $delivery = $action->execute($webhookEndpoint);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: new WebhookDeliveryResource($delivery),
            message: 'Webhook Test Ping Queued Successfully',
            statusCode: 202,
        );
    }
}
