<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clients;

use App\Actions\ApiClients\CreateApiClientAction;
use App\DataTransferObjects\ApiClients\CreateApiClientData;
use App\Http\Requests\ApiClients\StoreClientRequest;
use App\Http\Resources\ApiClientResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Creates a service account and linked API client credentials.
 *
 * @example
 * POST /api/clients {"name":"Billing Sync","abilities":["users.list","users.list-all"]}
 */
final class StoreClientController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Create a service User and API client credentials.
     *
     * @param  StoreClientRequest    $request the validated create-client request
     * @param  CreateApiClientAction $action  the create-client Action
     * @return JsonResponse          the standardised success envelope
     */
    public function __invoke(
        StoreClientRequest $request,
        CreateApiClientAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        $data = new CreateApiClientData(
            name: $input->string('name')->toString(),
            abilities: $request->tokenAbilities(),
        );

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $result = $action->execute($data);

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
            message: 'API Client Created Successfully',
            statusCode: 201,
        );
    }
}
