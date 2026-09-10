<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\Webhooks\UpdateWebhookEndpointAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\DataTransferObjects\Webhooks\UpdateWebhookEndpointData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Webhooks\UpdateWebhookEndpointRequest;
use App\Http\Resources\WebhookEndpointResource;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\ApiResponse;
use App\Support\RequestId;
use Illuminate\Http\JsonResponse;

/**
 * Updates a webhook endpoint.
 *
 * @example
 * PATCH /api/webhook-endpoints/{webhook_endpoint} {"name": "Billing V2"}
 */
final class UpdateWebhookEndpointController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Update the given endpoint.
     *
     * @param  UpdateWebhookEndpointRequest $request         the validated update request
     * @param  WebhookEndpoint              $webhookEndpoint the endpoint being updated (route-bound)
     * @param  UpdateWebhookEndpointAction  $action          the update Action
     * @return JsonResponse                 the standardised success envelope
     */
    public function __invoke(
        UpdateWebhookEndpointRequest $request,
        WebhookEndpoint $webhookEndpoint,
        UpdateWebhookEndpointAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        /** @var list<string>|null $events */
        $events = $input->has('events') ? $input->array('events') : null;

        $data = new UpdateWebhookEndpointData(
            endpoint: $webhookEndpoint,
            name: $input->has('name') ? $input->string('name')->toString() : null,
            url: $input->has('url') ? $input->string('url')->toString() : null,
            events: $events,
            isActive: $input->has('is_active') ? $input->boolean('is_active') : null,
        );

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $updatedEndpoint = $action->execute($data);

        /** @var User $actor the route sits behind the authenticated group */
        $actor = $request->user();

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::WebhookEndpointUpdated,
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

        return ApiResponse::success(
            data: new WebhookEndpointResource($updatedEndpoint),
            message: 'Webhook Endpoint Updated Successfully',
        );
    }
}
