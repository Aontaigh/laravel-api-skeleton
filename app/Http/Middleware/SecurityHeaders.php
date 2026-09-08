<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

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
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * `max_age` advertised on the legacy `Report-To` header, in seconds.
     *
     * 10,886,400 seconds (18 weeks) matches Chrome's own historical NEL /
     * Report-To default - long enough that a browser does not need to
     * re-fetch the endpoint configuration on every visit.
     */
    private const int REPORT_TO_MAX_AGE_SECONDS = 10_886_400;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */
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

        if (! $this->shouldAttachCsp()) {
            return $response;
        }

        $cspHeader = config()->boolean('security.csp_enforce')
            ? 'Content-Security-Policy'
            : 'Content-Security-Policy-Report-Only';
        $headers->set($cspHeader, $this->resolveCspPolicy($request));

        /*
         * No CSP nonce is minted: no served page runs inline scripts (Scalar
         * loads an external bundle, Vite injects its own), so a nonce would
         * be dead header bytes. Revisit if an inline bootstrap ever lands.
         */
        $this->setReportingHeaders($headers);

        /*
         * Cookie-authenticated SPA responses carry PII and token metadata and
         * carry no RFC 7234 shared-cache protection (no Authorization header),
         * so nothing this API serves may be stored by a browser or proxy.
         */
        $headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the CSP should be attached to this response.
     *
     * Vite hot-reload injects scripts from the dev server that a strict CSP
     * would block, so local hot runs skip the header entirely. Locally-built
     * assets and every non-local environment still get the policy.
     *
     * @return bool true when the CSP should be attached
     */
    private function shouldAttachCsp(): bool
    {
        return ! (App::environment('local') && Vite::isRunningHot());
    }

    /**
     * Resolve the CSP for the current request path.
     *
     * @param  Request $request the inbound HTTP request
     * @return string  the CSP policy for the response
     */
    private function resolveCspPolicy(Request $request): string
    {
        if ($request->is('api/docs')) {
            return config()->string('security.csp_docs_policy');
        }

        return config()->string('security.csp_policy');
    }

    /**
     * Advertise the CSP report collector via both the modern and legacy headers.
     *
     * `Reporting-Endpoints` is the current W3C Reporting API mechanism Chrome
     * now expects; the deprecated `Report-To` header is sent alongside it for
     * older Chromium that has not adopted the replacement. Neither header is
     * read by browsers that only support `report-uri` (already set in the CSP
     * policy string itself), so sending all three is the current
     * belt-and-braces approach for reporting compatibility.
     *
     * @param  ResponseHeaderBag $headers the outgoing response headers
     * @return void
     */
    private function setReportingHeaders(ResponseHeaderBag $headers): void
    {
        $group = config()->string('security.csp_report_to_group');
        $reportUri = config()->string('security.csp_report_uri');

        $headers->set('Reporting-Endpoints', sprintf('%s="%s"', $group, $reportUri));

        $headers->set('Report-To', (string) json_encode([
            'group' => $group,
            'max_age' => self::REPORT_TO_MAX_AGE_SECONDS,
            'endpoints' => [['url' => $reportUri]],
        ]));
    }
}
