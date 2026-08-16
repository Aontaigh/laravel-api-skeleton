<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\FinaliseAuthenticatedSessionAction;
use App\Actions\Auth\VerifyTwoFactorCodeAction;
use App\DataTransferObjects\Auth\FinaliseAuthenticatedSessionData;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Exceptions\Auth\TwoFactorChallengeException;
use App\Http\Requests\Auth\VerifyTwoFactorRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Auth\PendingTwoFactor;
use Illuminate\Http\JsonResponse;

/**
 * Verifies the submitted two-factor code and finalises the login.
 *
 * Pending state is resolved from the session cookie or the opaque
 * `two_factor_token` returned by login or register.
 *
 * @example
 * POST /api/two-factor/verify {"code":"123456","two_factor_token":"..."}
 */
final class VerifyTwoFactorController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Verify the submitted code and finalise the login.
     *
     * Resolves the pending challenge from the session or optional opaque
     * token. Any failure (no challenge, unknown user, wrong/expired code)
     * returns the same generic 422 so nothing about the challenge state leaks.
     *
     * @param  VerifyTwoFactorRequest             $request  the validated code request
     * @param  VerifyTwoFactorCodeAction          $verify   the verify Action
     * @param  FinaliseAuthenticatedSessionAction $finalise the session finalisation Action
     * @return JsonResponse                       the authenticated User, or a generic error
     */
    public function __invoke(
        VerifyTwoFactorRequest $request,
        VerifyTwoFactorCodeAction $verify,
        FinaliseAuthenticatedSessionAction $finalise,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        $code = $input->string('code')->toString();

        /*
        |--------------------------------------------------------------------------
        | Guard
        |--------------------------------------------------------------------------
        |
        | The pending challenge lives in the session or opaque token between
        | credential verification and here. No pending challenge means no login
        | is mid-flight, so we return the same generic failure used for a bad code.
        |
        */

        $pending = PendingTwoFactor::resolve($request->twoFactorToken());

        if ($pending === null) {
            return $this->genericFailure();
        }

        $user = User::query()->find($pending->userId);

        if (! $user instanceof User) {
            return $this->genericFailure();
        }

        /*
        |--------------------------------------------------------------------------
        | Reject Suspension Opened Mid-Flight
        |--------------------------------------------------------------------------
        |
        | The account can be suspended between the credential step and here; re-
        | check so a challenge opened while active can never be completed into a
        | live session once suspended. Mirrors the login-time gate.
        |
        */

        if ($user->isSuspended()) {
            return ApiResponse::error(message: 'Account Suspended', statusCode: 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify
        |--------------------------------------------------------------------------
        |
        | A wrong, expired, or over-attempt code throws and surfaces the same
        | generic 422, so nothing about the challenge state ever leaks.
        |
        */

        try {
            $verify->execute($user, $code, $pending->token);
        } catch (TwoFactorChallengeException $exception) {
            AuthEventOccurred::dispatch(new RecordAuthAuditData(
                event: AuthAuditEvent::TwoFactorFailed,
                userId: $user->id,
                email: $user->email,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ));

            return ApiResponse::error(message: $exception->getMessage(), statusCode: 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Finalise
        |--------------------------------------------------------------------------
        |
        | The code was correct. Clear the pending challenge, issue the token,
        | and audit both the verification and the sign-in.
        |
        */

        $deviceName = $pending->deviceName
            ?? ($input->filled('device_name') ? $input->string('device_name')->toString() : 'API Session');

        $newToken = $finalise->execute(new FinaliseAuthenticatedSessionData(
            user: $user,
            deviceName: $deviceName,
            remember: $pending->shouldRemember,
            ipAddress: (string) $request->ip(),
            userAgent: $request->userAgent(),
            regenerateSession: $request->hasSession(),
        ));

        PendingTwoFactor::forget($pending->token);

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::TwoFactorVerified,
            userId: $user->id,
            email: $user->email,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
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

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Build the shared generic two-factor failure response.
     *
     * @return JsonResponse the generic 422 response
     */
    private function genericFailure(): JsonResponse
    {
        return ApiResponse::error(message: 'Invalid or Expired Code', statusCode: 422);
    }
}
