<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sessions;

use App\Actions\Sessions\RevokeWebSessionAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Sessions\DestroySessionRequest;
use App\Models\User;
use App\Models\WebSession;
use App\Support\ApiResponse;
use App\Support\RequestId;
use Illuminate\Http\JsonResponse;

/**
 * Revokes one registered cookie-bound web session.
 *
 * @example
 * DELETE /api/sessions/{web_session}
 */
final class DestroySessionController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Revoke the given web session.
     *
     * @param  DestroySessionRequest  $request    the validated revoke-session request
     * @param  WebSession             $webSession the session being revoked (route-bound)
     * @param  RevokeWebSessionAction $action     the revoke-session Action
     * @return JsonResponse           the standardised success envelope
     */
    public function __invoke(
        DestroySessionRequest $request,
        WebSession $webSession,
        RevokeWebSessionAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $action->execute($webSession, $request);

        /*
         * The row carries the session owner, matching force-logout: an Admin
         * revoking a foreign session is attributed to the affected account,
         * with the actor recoverable from the recorded IP and user agent.
         * The e-mail is read with a direct query so serialising the row never
         * triggers a lazy load under `preventLazyLoading()`.
         */
        $ownerEmail = User::query()->whereKey($webSession->user_id)->value('email');

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::SessionRevoked,
            userId: $webSession->user_id,
            email: is_string($ownerEmail) ? $ownerEmail : null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            requestId: RequestId::current($request),
        ));

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(data: null, message: 'Session Revoked Successfully');
    }
}
