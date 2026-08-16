<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LogoutUserAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Auth\LogoutRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Ends the authenticated User's session and revokes every issued token.
 *
 * @example
 * POST /api/logout
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

        $auditData = new RecordAuthAuditData(
            event: AuthAuditEvent::Logout,
            userId: $user->id,
            email: $user->email,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
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
