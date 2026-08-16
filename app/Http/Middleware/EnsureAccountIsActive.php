<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects an authenticated request from a suspended account.
 *
 * Runs on every authenticated route for both auth modes, so it covers
 * Bearer-token (Personal Access Token) clients as well as any cookie session
 * that outlives a suspension. A suspended User is turned away with a 403
 * regardless of how it authenticated — the gate is the single place that
 * revokes a live User's access.
 */
final class EnsureAccountIsActive
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Turn away a suspended account with a 403; pass everyone else through.
     *
     * @param  Request                    $request the incoming request
     * @param  Closure(Request): Response $next    the next pipeline stage
     * @return Response                   the downstream response, or a 403 when suspended
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isSuspended()) {
            return ApiResponse::error(message: 'Account Suspended', statusCode: 403);
        }

        return $next($request);
    }
}
