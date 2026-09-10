<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clients;

use App\Actions\ApiClients\RotateApiClientSecretAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Clients\RotateClientSecretRequest;
use App\Http\Resources\ApiClientResource;
use App\Models\ApiClient;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\RequestId;
use Illuminate\Http\JsonResponse;

/**
 * Rotates an API client's secret, returning the new one exactly once.
 *
 * @example
 * POST /api/clients/{client}/rotate-secret
 */
final class RotateClientSecretController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Rotate the given client's secret.
     *
     * @param  RotateClientSecretRequest   $request the validated rotate request
     * @param  ApiClient                   $client  the client being rotated (route-bound)
     * @param  RotateApiClientSecretAction $action  the rotate-secret Action
     * @return JsonResponse                the standardised success envelope
     */
    public function __invoke(
        RotateClientSecretRequest $request,
        ApiClient $client,
        RotateApiClientSecretAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $result = $action->execute($client);

        /*
         * The rotation is an access-control event: record it against the
         * acting Admin with the rotated client bound, so incident response
         * can trace every credential change.
         */

        /** @var User $actor the route sits behind the authenticated group */
        $actor = $request->user();

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::ClientSecretRotated,
            userId: $actor->id,
            email: $actor->email,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            apiClientId: $client->id,
            requestId: RequestId::current($request),
        ));

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: [
                'client' => new ApiClientResource($result->client),
                'client_secret' => $result->plainTextSecret,
            ],
            message: 'Client Secret Rotated Successfully',
        );
    }
}
