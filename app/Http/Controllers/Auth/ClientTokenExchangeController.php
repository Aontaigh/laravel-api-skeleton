<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ExchangeClientCredentialsAction;
use App\DataTransferObjects\Auth\ClientCredentialsData;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Auth\ClientTokenExchangeRequest;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Issues a Sanctum bearer token via the OAuth2 client-credentials grant.
 *
 * @example
 * POST /api/oauth/token {"grant_type":"client_credentials","client_id":"...","client_secret":"..."}
 */
final class ClientTokenExchangeController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Exchange client credentials for a scoped bearer token.
     *
     * @param  ClientTokenExchangeRequest      $request  the validated exchange request
     * @param  ExchangeClientCredentialsAction $exchange the credential exchange Action
     * @return JsonResponse                    the standardised success envelope
     */
    public function __invoke(
        ClientTokenExchangeRequest $request,
        ExchangeClientCredentialsAction $exchange,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        $credentials = new ClientCredentialsData(
            clientId: $input->string('client_id')->toString(),
            clientSecret: $input->string('client_secret')->toString(),
        );

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        try {
            $result = $exchange->execute(
                credentials: $credentials,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        } catch (ValidationException $exception) {
            AuthEventOccurred::dispatch(new RecordAuthAuditData(
                event: AuthAuditEvent::ClientTokenExchangeFailed,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ));

            throw $exception;
        }

        $newToken = $result['token'];

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        $days = config()->integer('api.client_token_expiration_days');
        $expiresIn = $days > 0 ? $days * 24 * 60 * 60 : null;

        return ApiResponse::success(
            data: [
                'token' => new PersonalAccessTokenResource($newToken->accessToken),
                'plain_text_token' => $newToken->plainTextToken,
                'expires_in' => $expiresIn,
            ],
            message: 'Token Issued Successfully',
        );
    }
}
