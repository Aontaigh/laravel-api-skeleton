<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks an authenticated request from an account with an unverified e-mail.
 *
 * Applied to the business route groups only. The identity endpoints
 * (`POST /auth/email/resend`, `POST /logout`, `GET /me`) deliberately sit
 * outside this gate so the SPA can still route an unverified User to the
 * verification screen and let them sign out. Service accounts pass through:
 * they authenticate via client credentials, never email, so there is no
 * mailbox to verify - and blocking them would disable machine-to-machine access.
 */
final class EnsureEmailIsVerified
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Reject the request when the authenticated account has an unverified
     * e-mail address.
     *
     * @param  Request                    $request the incoming request
     * @param  Closure(Request): Response $next    the next pipeline stage
     * @return Response                   the downstream response, or a 403 when unverified
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->hasVerifiedEmail() && ! $user->isServiceAccount()) {
            return ApiResponse::error(message: 'E-Mail Not Verified', statusCode: 403);
        }

        return $next($request);
    }
}
