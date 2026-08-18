<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RestoreUserFromRememberAction;
use App\Actions\Sessions\RegisterWebSessionAction;
use App\Actions\Tokens\CreatePersonalAccessTokenAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\DataTransferObjects\Sessions\RegisterWebSessionData;
use App\DataTransferObjects\Tokens\CreateTokenData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Auth\RememberLoginRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\ValidatedInput;

/**
 * Restores a User from a remember-me cookie or session and issues a fresh token.
 *
 * @example
 * POST /api/auth/login/remember
 */
final class RememberLoginController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Re-authenticate via remember-me and return a bearer token.
     *
     * @param  RememberLoginRequest            $request    the authorised remember request
     * @param  RestoreUserFromRememberAction   $restore    the session restoration Action
     * @param  CreatePersonalAccessTokenAction $issueToken the token issuance Action
     * @param  RegisterWebSessionAction        $register   the web session registry Action
     * @return JsonResponse                    the standardised success envelope
     */
    public function __invoke(
        RememberLoginRequest $request,
        RestoreUserFromRememberAction $restore,
        CreatePersonalAccessTokenAction $issueToken,
        RegisterWebSessionAction $register,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Restore
        |--------------------------------------------------------------------------
        |
        | There is no request payload to read first: the session is restored from
        | the remember-me cookie, and both failure modes answer with the same
        | generic 401 so a suspended account is indistinguishable from a bad
        | cookie.
        |
        */

        /** @var User|null $user */
        $user = $restore->execute();

        if ($user === null) {
            return ApiResponse::error(
                message: 'Unauthenticated',
                statusCode: 401,
            );
        }

        if ($user->isServiceAccount() || $user->isSuspended()) {
            return ApiResponse::error(
                message: 'Unauthenticated',
                statusCode: 401,
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        |
        | Rotate the session id at the privilege boundary before issuing the
        | token: a restored remember-me session is still crossing from
        | unauthenticated to authenticated, so any fixated pre-auth id must not
        | survive. Guarded because a bearer-token client has no session bound.
        |
        */

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        if (session()->isStarted()) {
            session()->put('session_version', $user->session_version);

            $register->execute(new RegisterWebSessionData(
                user: $user,
                sessionId: session()->getId(),
                deviceName: $this->deviceName($input),
                ipAddress: (string) $request->ip(),
                userAgent: $request->userAgent(),
                rememberMe: true,
            ));
        }

        $newToken = $issueToken->execute(new CreateTokenData(
            forUser: $user,
            name: $this->deviceName($input),
            abilities: ['*'],
            remember: true,
        ));

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::RememberMeLogin,
            userId: $user->id,
            email: $user->email,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            personalAccessTokenId: $newToken->accessToken->id,
            rememberMe: true,
        ));

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: [
                'user' => new AuthenticatedUserResource($user),
                'token' => new PersonalAccessTokenResource($newToken->accessToken),
                'plain_text_token' => $newToken->plainTextToken,
            ],
            message: 'Session Restored Successfully',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the Sanctum token label from validated input.
     *
     * @param  ValidatedInput $input the validated request payload
     * @return string         the device label
     */
    private function deviceName(ValidatedInput $input): string
    {
        if ($input->filled('device_name')) {
            return $input->string('device_name')->toString();
        }

        return 'API Session';
    }
}
