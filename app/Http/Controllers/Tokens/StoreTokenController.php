<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tokens;

use App\Actions\Tokens\CreatePersonalAccessTokenAction;
use App\DataTransferObjects\Tokens\CreateTokenData;
use App\Http\Requests\Tokens\StoreTokenRequest;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Issues a new Personal Access Token for the authenticated User.
 *
 * @example
 * POST /api/tokens {"name": "CLI Token"}
 */
final class StoreTokenController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Create a Token for the authenticated User.
     *
     * The plaintext value is returned only once, in this response — it is
     * never retrievable again, matching Sanctum's own guarantee.
     *
     * @param  StoreTokenRequest               $request the validated create-Token request
     * @param  CreatePersonalAccessTokenAction $action  the create-Token Action
     * @return JsonResponse                    the standardised success envelope
     */
    public function __invoke(StoreTokenRequest $request, CreatePersonalAccessTokenAction $action): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        $data = new CreateTokenData(
            forUser: $request->viewer(),
            name: $input->string('name')->toString(),
            abilities: $request->tokenAbilities(),
        );

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $newToken = $action->execute($data);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: [
                'token' => new PersonalAccessTokenResource($newToken->accessToken),
                'plain_text_token' => $newToken->plainTextToken,
            ],
            message: 'Token Created Successfully',
            statusCode: 201,
        );
    }
}
