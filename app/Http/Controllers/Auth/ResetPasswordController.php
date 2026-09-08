<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResetUserPasswordAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Enums\PasswordChangeSource;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Notifications\Auth\PasswordChangedNotification;
use App\Services\UserAgent\Contracts\UserAgentParser;
use App\Support\ApiResponse;
use App\Support\RequestId;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * Resets a password from a valid reset token and revokes every credential.
 *
 * @example
 * POST /api/auth/reset-password {"token": "...", "email": "alice@example.com", "password": "..."}
 */
final class ResetPasswordController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Reset the password against a valid token.
     *
     * On a valid token the Action performs a full credential rotation: every
     * Personal Access Token is revoked (the `session_version` guard cannot
     * reach Bearer tokens), every registered web session is stamped revoked
     * and its stored payload destroyed, and the `session_version` bump kills
     * every cookie session on its next request. An invalid or expired token
     * yields a generic 422 that does not distinguish the two cases.
     *
     * @param  ResetPasswordRequest    $request         the validated reset request
     * @param  ResetUserPasswordAction $resetPassword   the credential rotation Action
     * @param  UserAgentParser         $userAgentParser the configured parser driver, resolved from the container
     * @return JsonResponse            the success envelope, or a 422 error
     */
    public function __invoke(
        ResetPasswordRequest $request,
        ResetUserPasswordAction $resetPassword,
        UserAgentParser $userAgentParser,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        $credentials = [
            'email' => $input->string('email')->toString(),
            'password' => $input->string('password')->toString(),
            'token' => $input->string('token')->toString(),
        ];

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $status = Password::reset(
            $credentials,
            static function (User $user, string $password) use ($resetPassword): void {
                $resetPassword->execute($user, $password);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            /*
             * Brute-forcing reset tokens must be visible to a SOC: record the
             * failed attempt (with the attempted address) before the generic
             * rejection - the response itself never distinguishes the cause.
             */
            $attempted = User::query()->where('email', $credentials['email'])->first();

            event(new AuthEventOccurred(new RecordAuthAuditData(
                event: AuthAuditEvent::PasswordResetFailed,
                userId: $attempted?->id,
                email: $credentials['email'],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                requestId: RequestId::current($request),
            )));

            return ApiResponse::error(
                message: 'The Reset Token Is Invalid Or Has Expired',
                statusCode: 422,
            );
        }

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user !== null) {
            event(new PasswordResetEvent($user));

            event(new AuthEventOccurred(new RecordAuthAuditData(
                event: AuthAuditEvent::PasswordReset,
                userId: $user->id,
                email: $credentials['email'],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                requestId: RequestId::current($request),
            )));

            /*
            |--------------------------------------------------------------------------
            | Password Changed Notification
            |--------------------------------------------------------------------------
            |
            | Best-effort: queuing the security alert is wrapped so a dispatch-time
            | failure is logged, not a 500, once the password is already saved.
            |
            */

            try {
                $user->notify(new PasswordChangedNotification(
                    source: PasswordChangeSource::ResetLink,
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                    userAgentParser: $userAgentParser,
                ));
            } catch (Throwable $exception) {
                Log::warning('Password Changed E-Mail Could Not Be Sent', [
                    'user_id' => $user->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: null,
            message: 'Password Reset Successfully',
        );
    }
}
