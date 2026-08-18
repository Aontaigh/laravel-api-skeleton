<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sessions;

use App\Actions\Sessions\RevokeWebSessionAction;
use App\Http\Requests\Sessions\DestroySessionRequest;
use App\Models\WebSession;
use App\Support\ApiResponse;
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
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(data: null, message: 'Session Revoked Successfully');
    }
}
