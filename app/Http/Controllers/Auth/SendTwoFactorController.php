<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\IssueTwoFactorChallengeAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Auth\SendTwoFactorRequest;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Auth\PendingTwoFactor;
use Illuminate\Http\JsonResponse;

/**
 * Dispatches a two-factor code over the chosen channel for the pending login.
 *
 * @example
 * POST /api/two-factor/send {"channel":"email","two_factor_token":"..."}
 */
final class SendTwoFactorController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Dispatch a two-factor code over the chosen channel.
     *
     * Login now only verifies credentials; the visitor then picks how to receive
     * their code. Only email is wired up — the code is dispatched from this
     * controller, not from login.
     *
     * @param  SendTwoFactorRequest          $request the validated channel request
     * @param  IssueTwoFactorChallengeAction $issue   the OTP issue Action
     * @return JsonResponse                  the code-sent envelope, or a clear error
     */
    public function __invoke(
        SendTwoFactorRequest $request,
        IssueTwoFactorChallengeAction $issue,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $channel = $request->channel();

        /*
        |--------------------------------------------------------------------------
        | Guard
        |--------------------------------------------------------------------------
        |
        | A code can only be sent while a login is mid-flight. The pending user id
        | comes from the caller's own session or opaque token — never from email
        | input — so there is no enumeration risk in returning a clear expiry
        | message here.
        |
        */

        $pending = PendingTwoFactor::resolve($request->twoFactorToken());
        $user = $pending === null ? null : User::query()->find($pending->userId);

        if (! $user instanceof User) {
            return ApiResponse::error(message: 'Your Sign-In Session Has Expired', statusCode: 422);
        }

        if ($user->isSuspended()) {
            return ApiResponse::error(message: 'Account Suspended', statusCode: 403);
        }

        if ($user->mfa_method !== $channel) {
            return ApiResponse::error(message: 'Invalid Channel', statusCode: 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Issue
        |--------------------------------------------------------------------------
        */

        $issue->execute(
            $user,
            preserveAttempts: IssueTwoFactorChallengeAction::hasChallenge($user),
        );

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::TwoFactorIssued,
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
            data: ['two_factor_required' => true],
            message: 'Two-Factor Code Sent',
        );
    }
}
