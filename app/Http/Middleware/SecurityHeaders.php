<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attaches the baseline security headers to every response.
 *
 * Covers clickjacking (`X-Frame-Options`), MIME sniffing (`X-Content-Type-Options`),
 * referrer leakage, and a permissions policy on all responses, plus HSTS on
 * secure/production traffic and a Content Security Policy. The API itself returns
 * JSON, so the CSP primarily protects the HTML API-docs and welcome pages.
 */
final class SecurityHeaders
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Attach the security headers to the outgoing response.
     *
     * @param  Request                    $request the incoming request
     * @param  Closure(Request): Response $next    the next pipeline stage
     * @return Response                   the response with security headers set
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), browsing-topics=()');

        /*
         * HSTS only matters over TLS; skip it on plain-http local dev.
         */
        if (App::environment('production', 'staging') || $request->isSecure()) {
            $headers->set(
                'Strict-Transport-Security',
                sprintf('max-age=%d; includeSubDomains; preload', config()->integer('security.hsts_max_age')),
            );
        }

        $cspHeader = config()->boolean('security.csp_enforce')
            ? 'Content-Security-Policy'
            : 'Content-Security-Policy-Report-Only';
        $headers->set($cspHeader, config()->string('security.csp_policy'));

        return $response;
    }
}
