<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Invalidates a web session whose stamped version no longer matches the User's.
 *
 * When a User's `session_version` is bumped (credential change, force-logout),
 * every session still carrying the old stamp is turned away with a 401 on its
 * next request. This is the driver-agnostic "log out everywhere" mechanism: it
 * works whether sessions live in the database, Redis, or files, because it
 * reads the stamp from the session payload rather than deleting store rows.
 *
 * Bearer-token clients are untouched — a stateless request carries no
 * `session_version`, so the gate only ever applies to cookie sessions.
 */
final class EnsureSessionVersionMatches
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Turn away a stale session with a 401; pass everyone else through.
     *
     * On a stale stamp the session is invalidated before responding so the
     * client cannot retry with the same fixated or superseded session id.
     *
     * @param  Request                    $request the incoming request
     * @param  Closure(Request): Response $next    the next pipeline stage
     * @return Response                   the downstream response, or a 401 when stale
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        /*
         * A stateful Origin can bind a session even when the caller authenticated
         * with a Bearer token. Session versioning only applies to cookie sessions.
         */
        if ($request->bearerToken() !== null) {
            return $next($request);
        }

        $session = $request->session();
        $stamped = $session->get('session_version');

        if (! is_numeric($stamped) || (int) $stamped !== $user->session_version) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            return ApiResponse::error(message: 'Session Expired', statusCode: 401);
        }

        return $next($request);
    }
}
