<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sessions;

use App\Actions\Sessions\RevokeWebSessionAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Sessions\DestroyCurrentSessionRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Revokes the caller's current cookie-bound browser session.
 *
 * @example
 * DELETE /api/sessions/current
 */
final class DestroyCurrentSessionController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Revoke the inbound Laravel session without touching bearer tokens.
     *
     * @param  DestroyCurrentSessionRequest $request the validated current-session request
     * @param  RevokeWebSessionAction       $action  the revoke-session Action
     * @return JsonResponse                 the standardised success envelope
     */
    public function __invoke(
        DestroyCurrentSessionRequest $request,
        RevokeWebSessionAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Resolve
        |--------------------------------------------------------------------------
        */

        $webSession = $request->currentWebSession();

        if ($webSession === null) {
            return ApiResponse::error(
                message: 'No Active Browser Session Found',
                statusCode: 404,
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $action->execute($webSession, $request);

        /** @var User $user the route sits behind the authenticated group */
        $user = $request->user();

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::SessionRevoked,
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

        return ApiResponse::success(data: null, message: 'Session Revoked Successfully');
    }
}
