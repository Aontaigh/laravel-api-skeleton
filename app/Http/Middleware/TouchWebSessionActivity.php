<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Sessions\TouchWebSessionActivityAction;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refreshes last-activity on the registry row matching the inbound session.
 *
 * Thin HTTP adapter: the throttled SQL lives in TouchWebSessionActivityAction.
 * Runs after `session.version` on the same authenticated group so a stale
 * stamp is torn down before a touch can rewrite last_activity_at on a dead
 * session.
 */
final class TouchWebSessionActivity
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new TouchWebSessionActivity middleware.
     *
     * @param TouchWebSessionActivityAction $touchActivity stamps last_activity_at when stale
     */
    public function __construct(
        private readonly TouchWebSessionActivityAction $touchActivity,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Touch the registry row for the inbound cookie session.
     *
     * Bearer-token clients are skipped: Personal Access Tokens are not
     * governed by the `web_sessions` registry.
     *
     * @param  Request                    $request the incoming request
     * @param  Closure(Request): Response $next    the next pipeline stage
     * @return Response                   the downstream response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user instanceof User
            && $request->hasSession()
            && $request->bearerToken() === null
        ) {
            $this->touchActivity->execute($request->session()->getId());
        }

        return $next($request);
    }
}
