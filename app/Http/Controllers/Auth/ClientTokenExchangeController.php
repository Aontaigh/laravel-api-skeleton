<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ExchangeClientCredentialsAction;
use App\DataTransferObjects\Auth\ClientCredentialsData;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuditOutcome;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Exceptions\Auth\ClientCredentialRefusedException;
use App\Http\Requests\Auth\ClientTokenExchangeRequest;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Support\ApiResponse;
use App\Support\RequestId;
use App\Support\TokenTtl;
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
                requestId: RequestId::current($request),
            );
        } catch (ClientCredentialRefusedException $refusal) {
            /*
             * The secret verified and the application then declined, so this is a
             * refusal and not an authentication failure. The principal is attributed
             * because it is known - attributing it only where the credential proved
             * out is what keeps the wrong-secret path below from becoming an
             * account-enumeration oracle.
             */
            AuthEventOccurred::dispatch(new RecordAuthAuditData(
                event: AuthAuditEvent::ClientTokenExchangeFailed,
                outcome: AuditOutcome::Refused,
                clientIneligibilityReason: $refusal->reason,
                userId: $refusal->owner?->id,
                email: $refusal->owner?->email,
                apiClientId: $refusal->client?->id,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                requestId: RequestId::current($request),
            ));

            throw ValidationException::withMessages([
                'client_id' => ['Invalid Credentials'],
            ]);
        } catch (ValidationException $exception) {
            AuthEventOccurred::dispatch(new RecordAuthAuditData(
                event: AuthAuditEvent::ClientTokenExchangeFailed,
                outcome: AuditOutcome::Failed,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                requestId: RequestId::current($request),
            ));

            throw $exception;
        }

        $newToken = $result['token'];

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        /*
         * The TTL is read from the minted token's own `expires_at` rather
         * than recomputed from configuration: the field is what Sanctum
         * enforces at guard time, so the reported lifetime can never drift
         * from what the caller actually holds.
         */
        $expiresIn = TokenTtl::secondsFor($newToken->accessToken->expires_at, now());

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
