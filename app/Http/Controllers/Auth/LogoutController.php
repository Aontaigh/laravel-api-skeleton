<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LogoutUserAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuditOutcome;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Auth\LogoutRequest;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\PresentingToken;
use App\Support\RequestId;
use Illuminate\Http\JsonResponse;

/**
 * Ends the authenticated User's session and revokes every issued token.
 *
 * @example
 * POST /api/auth/logout
 */
final class LogoutController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Log out the current User everywhere.
     *
     * @param  LogoutRequest    $request the authorised logout request
     * @param  LogoutUserAction $logout  the logout Action
     * @return JsonResponse     the standardised success envelope
     */
    public function __invoke(
        LogoutRequest $request,
        LogoutUserAction $logout,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        /** @var User $user */
        $user = $request->user();

        /*
         * Capture the presenting credential before the action revokes it: the
         * audit row identifies which token performed the logout, and a
         * cookie-session caller (TransientToken) carries none.
         */
        $presentingToken = $user->currentAccessToken();
        $presentingTokenId = PresentingToken::isPersonalAccessToken($presentingToken) ? $presentingToken->id : null;

        $auditData = new RecordAuthAuditData(
            event: AuthAuditEvent::Logout,
            outcome: AuditOutcome::Succeeded,
            userId: $user->id,
            email: $user->email,
            personalAccessTokenId: $presentingTokenId,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            requestId: RequestId::current($request),
        );

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        AuthEventOccurred::dispatch($auditData);

        $logout->execute($user, $request);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(data: null, message: 'Logged Out Successfully');
    }
}
