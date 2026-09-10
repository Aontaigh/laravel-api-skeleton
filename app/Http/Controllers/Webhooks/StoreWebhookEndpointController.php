<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\Webhooks\CreateWebhookEndpointAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\DataTransferObjects\Webhooks\CreateWebhookEndpointData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Webhooks\StoreWebhookEndpointRequest;
use App\Http\Resources\WebhookEndpointResource;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\RequestId;
use Illuminate\Http\JsonResponse;

/**
 * Creates a new webhook endpoint with a one-time signing secret.
 *
 * @example
 * POST /api/webhook-endpoints {"name":"Billing Sync","events":["user.created"]}
 */
final class StoreWebhookEndpointController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Create a webhook endpoint.
     *
     * @param  StoreWebhookEndpointRequest $request the validated creation request
     * @param  CreateWebhookEndpointAction $action  the create-endpoint Action
     * @return JsonResponse                the standardised success envelope
     */
    public function __invoke(
        StoreWebhookEndpointRequest $request,
        CreateWebhookEndpointAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        /** @var User $owner the route sits behind the authenticated group */
        $owner = $request->user();

        /** @var list<string> $events validated event identifiers decode as a list */
        $events = $input->array('events');

        $data = new CreateWebhookEndpointData(
            ownerId: $owner->id,
            name: $input->string('name')->toString(),
            url: $input->string('url')->toString(),
            events: $events,
        );

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $result = $action->execute($data);

        /** @var User $actor the route sits behind the authenticated group */
        $actor = $request->user();

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::WebhookEndpointCreated,
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
            data: [
                'endpoint' => new WebhookEndpointResource($result->endpoint),
                'webhook_secret' => $result->plainTextSecret,
            ],
            message: 'Webhook Endpoint Created Successfully',
            statusCode: 201,
        );
    }
}
