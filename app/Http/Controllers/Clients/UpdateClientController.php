<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clients;

use App\Actions\ApiClients\UpdateApiClientAction;
use App\DataTransferObjects\ApiClients\UpdateApiClientData;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\ApiClients\UpdateClientRequest;
use App\Http\Resources\ApiClientResource;
use App\Models\ApiClient;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\RequestId;
use Illuminate\Http\JsonResponse;

/**
 * Updates an API client's mutable fields.
 *
 * @example
 * PATCH /api/clients/{client} {"name":"Billing Sync","abilities":["users.list"],"is_active":false}
 */
final class UpdateClientController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Update an API client's name, abilities, or active status.
     *
     * @param  UpdateClientRequest   $request the authorised update request
     * @param  ApiClient             $client  the route-bound client
     * @param  UpdateApiClientAction $action  the update-client Action
     * @return JsonResponse          the standardised success envelope
     */
    public function __invoke(
        UpdateClientRequest $request,
        ApiClient $client,
        UpdateApiClientAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        $data = new UpdateApiClientData(
            name: $input->has('name') ? $input->string('name')->toString() : null,
            abilities: $request->optionalTokenAbilities(),
            isActive: $input->has('is_active') ? $input->boolean('is_active') : null,
        );

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $result = $action->execute($client, $data);

        /** @var User $actor the route sits behind the authenticated group */
        $actor = $request->user();

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::ApiClientUpdated,
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
            data: new ApiClientResource($result),
            message: 'API Client Updated Successfully',
        );
    }
}
