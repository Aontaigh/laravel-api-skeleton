<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clients;

use App\Actions\ApiClients\RevokeApiClientAction;
use App\Http\Requests\ApiClients\DestroyClientRequest;
use App\Models\ApiClient;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Deactivates an API client and revokes its service account tokens.
 *
 * @example
 * DELETE /api/clients/{client}
 */
final class DestroyClientController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Revoke an API client and every token on its service account.
     *
     * @param  DestroyClientRequest  $request the authorised revoke request
     * @param  ApiClient             $client  the route-bound client
     * @param  RevokeApiClientAction $action  the revoke-client Action
     * @return JsonResponse          the standardised success envelope
     */
    public function __invoke(
        DestroyClientRequest $request,
        ApiClient $client,
        RevokeApiClientAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $action->execute($client);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: null,
            message: 'API Client Revoked Successfully',
        );
    }
}
