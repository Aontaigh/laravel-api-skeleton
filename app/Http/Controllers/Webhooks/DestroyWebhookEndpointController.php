<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\Webhooks\DeleteWebhookEndpointAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Webhooks\DestroyWebhookEndpointRequest;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\ApiResponse;
use App\Support\RequestId;
use Illuminate\Http\JsonResponse;

/**
 * Deletes a webhook endpoint and its delivery history.
 *
 * @example
 * DELETE /api/webhook-endpoints/{webhook_endpoint}
 */
final class DestroyWebhookEndpointController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Delete the given endpoint.
     *
     * @param  DestroyWebhookEndpointRequest $request         the validated delete request
     * @param  WebhookEndpoint               $webhookEndpoint the endpoint being deleted (route-bound)
     * @param  DeleteWebhookEndpointAction   $action          the delete Action
     * @return JsonResponse                  the standardised success envelope
     */
    public function __invoke(
        DestroyWebhookEndpointRequest $request,
        WebhookEndpoint $webhookEndpoint,
        DeleteWebhookEndpointAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $action->execute($webhookEndpoint);

        /** @var User $actor the route sits behind the authenticated group */
        $actor = $request->user();

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::WebhookEndpointDeleted,
            userId: $actor->id,
            email: $actor->email,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            requestId: RequestId::current($request),
        ));

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(data: null, message: 'Webhook Endpoint Deleted Successfully');
    }
}
