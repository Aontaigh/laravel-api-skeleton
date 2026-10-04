<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sessions;

use App\Actions\Sessions\RevokeWebSessionAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuditOutcome;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Sessions\DestroyCurrentSessionRequest;
use App\Queries\Sessions\CurrentWebSessionQuery;
use App\Support\ApiResponse;
use App\Support\RequestId;
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
     * @param  DestroyCurrentSessionRequest $request             the validated current-session request
     * @param  RevokeWebSessionAction       $action              the revoke-session Action
     * @param  CurrentWebSessionQuery       $currentSessionQuery resolves the caller's current registry row
     * @return JsonResponse                 the standardised success envelope
     */
    public function __invoke(
        DestroyCurrentSessionRequest $request,
        RevokeWebSessionAction $action,
        CurrentWebSessionQuery $currentSessionQuery,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Resolve
        |--------------------------------------------------------------------------
        */

        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error(
                message: 'No Active Browser Session Found',
                statusCode: 404,
            );
        }

        $webSession = $currentSessionQuery->resolve(
            $user,
            $request->hasSession() ? $request->session()->getId() : null,
        );

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

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::SessionRevoked,
            outcome: AuditOutcome::Succeeded,
            userId: $user->id,
            email: $user->email,
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
