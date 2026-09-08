<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Email;

use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Auth\ResendVerificationRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Resends the e-mail verification link to the authenticated User.
 *
 * @example
 * POST /api/auth/email/resend
 */
final class ResendVerificationController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Resend the verification link to the current authenticated User.
     *
     * Takes no e-mail input (acts on the current user only), so it cannot be
     * used to enumerate or spam arbitrary addresses. Both answers are generic
     * and idempotent: an already-verified account gets the same 200 shape as
     * a fresh send, without dispatching another mail.
     *
     * @param  ResendVerificationRequest $request the validated request
     * @return JsonResponse              the generic success envelope
     */
    public function __invoke(ResendVerificationRequest $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        /** @var User $user the route sits behind the authenticated group */
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();

            AuthEventOccurred::dispatch(new RecordAuthAuditData(
                event: AuthAuditEvent::EmailVerificationSent,
                userId: $user->id,
                email: $user->email,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ));
        }

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: null,
            message: 'If the Account Is Unverified, a New Verification Link Has Been Sent',
        );
    }
}
