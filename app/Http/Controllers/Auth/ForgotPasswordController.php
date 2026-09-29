<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\RequestId;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * Sends a password reset link for an account identified by email.
 *
 * @example
 * POST /api/auth/forgot-password {"email": "alice@example.com"}
 */
final class ForgotPasswordController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Send a password reset link, always returning a generic success.
     *
     * The response never reveals whether the address exists (enumeration
     * hardening): the mail is only sent when the account is found, and the
     * broker's own throttle (60 seconds per address) silently swallows repeat
     * requests without changing the response.
     *
     * @param  ForgotPasswordRequest $request the validated request
     * @return JsonResponse          the generic success envelope
     */
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $email = $request->safe()->string('email')->toString();

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        |
        | A delivery failure (mail transport down, queue unreachable) is caught
        | and reported rather than allowed to escape: an unknown address never
        | reaches delivery and answers the same generic success, so surfacing
        | the failure would reveal which addresses have accounts. The request is
        | still audited below.
        |
        */

        try {
            Password::sendResetLink(['email' => $email]);
        } catch (Throwable $deliveryFailure) {
            report($deliveryFailure);
        }

        $user = User::query()->where('email', $email)->first();

        event(new AuthEventOccurred(new RecordAuthAuditData(
            event: AuthAuditEvent::PasswordResetRequested,
            userId: $user?->id,
            email: $email,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            requestId: RequestId::current($request),
        )));

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: null,
            message: 'If the Account Exists, a Reset Link Has Been Sent',
        );
    }
}
