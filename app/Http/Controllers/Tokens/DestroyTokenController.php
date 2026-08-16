<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tokens;

use App\Actions\Tokens\RevokePersonalAccessTokenAction;
use App\Http\Requests\Tokens\DestroyTokenRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Revokes one of the authenticated User's own Personal Access Tokens.
 *
 * @example
 * DELETE /api/tokens/{token}
 */
final class DestroyTokenController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Revoke the given Token.
     *
     * @param  DestroyTokenRequest             $request the validated revoke-Token request
     * @param  PersonalAccessToken             $token   the Token being revoked (route-bound)
     * @param  RevokePersonalAccessTokenAction $action  the revoke-Token Action
     * @return JsonResponse                    the standardised success envelope
     */
    public function __invoke(
        DestroyTokenRequest $request,
        PersonalAccessToken $token,
        RevokePersonalAccessTokenAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $action->execute($token);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(data: null, message: 'Token Revoked Successfully');
    }
}
