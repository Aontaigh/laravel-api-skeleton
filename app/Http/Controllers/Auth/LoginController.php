<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ApplyRememberMeAction;
use App\Actions\Auth\AuthenticateUserAction;
use App\Actions\Tokens\CreatePersonalAccessTokenAction;
use App\DataTransferObjects\Auth\LoginCredentialsData;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\DataTransferObjects\Tokens\CreateTokenData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Authenticates a User with email and password and issues a Sanctum token.
 *
 * @example
 * POST /api/login {"email": "alice@example.com", "password": "SecretPass12", "remember": true}
 */
final class LoginController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Verify credentials and return a bearer token.
     *
     * @param  LoginRequest                    $request       the validated login request
     * @param  AuthenticateUserAction          $authenticate  the credential check Action
     * @param  ApplyRememberMeAction           $applyRemember the remember-me Action
     * @param  CreatePersonalAccessTokenAction $issueToken    the token issuance Action
     * @return JsonResponse                    the standardised success envelope
     */
    public function __invoke(
        LoginRequest $request,
        AuthenticateUserAction $authenticate,
        ApplyRememberMeAction $applyRemember,
        CreatePersonalAccessTokenAction $issueToken,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        $credentials = new LoginCredentialsData(
            email: $input->string('email')->toString(),
            password: $input->string('password')->toString(),
            remember: $input->boolean('remember'),
        );

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        try {
            $user = $authenticate->execute($credentials);
        } catch (ValidationException $exception) {
            AuthEventOccurred::dispatch(new RecordAuthAuditData(
                event: AuthAuditEvent::LoginFailed,
                email: $credentials->email,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ));

            throw $exception;
        }

        if ($credentials->remember) {
            $applyRemember->execute($user);
            Auth::guard('web')->login($user->refresh(), true);

            /*
             * Rotate the session id at the privilege boundary so a pre-auth
             * (fixated) session id can no longer be used. Guarded: bearer-token
             * clients hit this controller with no session store bound.
             */
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }
        }

        $deviceName = $input->filled('device_name')
            ? $input->string('device_name')->toString()
            : 'API Session';

        $newToken = $issueToken->execute(new CreateTokenData(
            forUser: $user,
            name: $deviceName,
            abilities: ['*'],
            remember: $credentials->remember,
        ));

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::Login,
            userId: $user->id,
            email: $user->email,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            personalAccessTokenId: $newToken->accessToken->id,
            rememberMe: $credentials->remember,
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
            message: 'Logged In Successfully',
        );
    }
}
