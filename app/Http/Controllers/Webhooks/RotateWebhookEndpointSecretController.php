<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\Webhooks\RotateWebhookEndpointSecretAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Webhooks\RotateWebhookSecretRequest;
use App\Http\Resources\WebhookEndpointResource;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\ApiResponse;
use App\Support\RequestId;
use Illuminate\Http\JsonResponse;

/**
 * Rotates a webhook endpoint's signing secret, returning the new one once.
 *
 * @example
 * POST /api/webhook-endpoints/{webhook_endpoint}/rotate-secret
 */
final class RotateWebhookEndpointSecretController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Rotate the given endpoint's signing secret.
     *
     * @param  RotateWebhookSecretRequest        $request         the validated rotate request
     * @param  WebhookEndpoint                   $webhookEndpoint the endpoint being rotated (route-bound)
     * @param  RotateWebhookEndpointSecretAction $action          the rotate-secret Action
     * @return JsonResponse                      the standardised success envelope
     */
    public function __invoke(
        RotateWebhookSecretRequest $request,
        WebhookEndpoint $webhookEndpoint,
        RotateWebhookEndpointSecretAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $result = $action->execute($webhookEndpoint);

        /** @var User $actor the route sits behind the authenticated group */
        $actor = $request->user();

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::WebhookSecretRotated,
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
            message: 'Webhook Secret Rotated Successfully',
        );
    }
}
