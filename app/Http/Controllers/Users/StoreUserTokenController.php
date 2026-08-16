<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Tokens\CreatePersonalAccessTokenAction;
use App\DataTransferObjects\Tokens\CreateTokenData;
use App\Http\Requests\Users\StoreUserTokenRequest;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Issues a new Personal Access Token on behalf of another User (admin-only).
 *
 * @example
 * POST /api/users/{user}/tokens {"name": "Integration Token"}
 */
final class StoreUserTokenController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Create a Token for the given User.
     *
     * @param  StoreUserTokenRequest           $request the validated create-Token request
     * @param  User                            $user    the User the Token is issued to (route-bound)
     * @param  CreatePersonalAccessTokenAction $action  the create-Token Action
     * @return JsonResponse                    the standardised success envelope
     */
    public function __invoke(
        StoreUserTokenRequest $request,
        User $user,
        CreatePersonalAccessTokenAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        $data = new CreateTokenData(
            forUser: $user,
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
