<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateUserAction;
use App\Actions\Auth\FinaliseAuthenticatedSessionAction;
use App\DataTransferObjects\Auth\FinaliseAuthenticatedSessionData;
use App\DataTransferObjects\Auth\LoginCredentialsData;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Auth\PendingTwoFactor;
use Illuminate\Http\JsonResponse;
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
     * @param  LoginRequest                       $request      the validated login request
     * @param  AuthenticateUserAction             $authenticate the credential check Action
     * @param  FinaliseAuthenticatedSessionAction $finalise     the session finalisation Action
     * @return JsonResponse                       the standardised success envelope
     */
    public function __invoke(
        LoginRequest $request,
        AuthenticateUserAction $authenticate,
        FinaliseAuthenticatedSessionAction $finalise,
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

        /*
        |--------------------------------------------------------------------------
        | Two-Factor Gate
        |--------------------------------------------------------------------------
        |
        | When the User has MFA enrolled, the credential check is the first
        | factor only — a pending challenge is stashed in the session and the
        | caller must complete verification before a token is issued.
        |
        */

        if ($user->hasMfaEnabled()) {
            $deviceName = $input->filled('device_name')
                ? $input->string('device_name')->toString()
                : null;

            $twoFactorToken = PendingTwoFactor::begin($user->id, $credentials->remember, $deviceName);

            return ApiResponse::success(
                data: [
                    'two_factor_required' => true,
                    'two_factor_token' => $twoFactorToken,
                ],
                message: 'Two-Factor Required',
            );
        }

        $deviceName = $input->filled('device_name')
            ? $input->string('device_name')->toString()
            : 'API Session';

        $newToken = $finalise->execute(new FinaliseAuthenticatedSessionData(
            user: $user,
            deviceName: $deviceName,
            remember: $credentials->remember,
            ipAddress: (string) $request->ip(),
            userAgent: $request->userAgent(),
            regenerateSession: $request->hasSession(),
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
