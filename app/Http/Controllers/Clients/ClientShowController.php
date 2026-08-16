<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clients;

use App\Http\Requests\ApiClients\ClientShowRequest;
use App\Http\Resources\ApiClientResource;
use App\Models\ApiClient;
use App\Queries\ApiClients\ApiClientQueryConstraints;
use App\Queries\IndexFieldsQuery;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Returns a single API client by ID.
 *
 * @example
 * GET /api/clients/{client}?fields[api_clients]=id,name,client_id
 */
final class ClientShowController
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
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
     * Return the route-bound API client with optional sparse fieldsets.
     */
    public function __invoke(ClientShowRequest $request, ApiClient $client): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

        $clientFields = $request->apiClientFields();

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        */

        /** @var \Illuminate\Database\Eloquent\Builder<ApiClient> $query */
        $query = ApiClient::query()->whereKey($client->getKey());

        $this->fieldsQuery->apply(
            query: $query,
            requestedFields: $clientFields,
            allowedFields: ApiClientQueryConstraints::ALLOWED_FIELDS,
            table: ApiClientQueryConstraints::TABLE,
            requiredColumns: ApiClientQueryConstraints::requiredSelectColumns([]),
        );

        /** @var ApiClient $loadedClient */
        $loadedClient = $query->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: new ApiClientResource($loadedClient),
            message: 'API Client Retrieved Successfully',
        );
    }
}
