<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RequestId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures every request carries a correlation ID end to end.
 *
 * Resolves the ID once (`X-Request-ID`, then W3C `traceparent`, then a fresh
 * UUID), writes it back onto the inbound headers so downstream reads
 * (`RequestId::current()`, audit payloads) resolve the same value, stamps it
 * on the response, and attaches it to the log context for the rest of the
 * request. The ID is correlation metadata only - never authentication.
 */
final class EnsureRequestId
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the correlation ID and propagate it to the request, response, and logs.
     *
     * @param  Request                    $request the incoming request
     * @param  Closure(Request): Response $next    the next pipeline stage
     * @return Response                   the downstream response carrying `X-Request-ID`
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = RequestId::current($request);

        $request->headers->set('X-Request-ID', $requestId);

        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);

        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
