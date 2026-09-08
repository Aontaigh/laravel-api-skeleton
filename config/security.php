<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CSP Violation Reporting Endpoint
|--------------------------------------------------------------------------
|
| First-party receiver for the `report-uri` directive (`POST /api/csp-reports`).
| Built from `app.url` - `app.php` loads before this file alphabetically, so it
| is already populated - with a relative-path fallback (report-uri accepts one).
|
*/

/** @var mixed $appUrl */
$appUrl = config('app.url');

$cspReportUri = is_string($appUrl) && $appUrl !== ''
    ? rtrim($appUrl, '/').'/api/csp-reports'
    : '/api/csp-reports';

return [

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security
    |--------------------------------------------------------------------------
    |
    | Max-age (in seconds) advertised via the Strict-Transport-Security header
    | on secure responses. One year with subdomain + preload is the standard.
    | Sent only in production/staging or over TLS - plain-http local dev skips it.
    |
    */

    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Two policies: a strict default for JSON API responses, and a relaxed
    | docs policy for the Scalar page at `GET /api/docs` which loads its bundle
    | and inline bootstrap from jsDelivr. Set SECURITY_CSP_ENFORCE=false to fall
    | back to report-only while iterating. In `local`, CSP is omitted entirely
    | while Vite hot-reload is active.
    |
    | Both policies carry `report-uri` pointing at the first-party receiver
    | above; violations land in the `csp-reports` log channel, never the main log.
    |
    */

    'csp_enforce' => (bool) env('SECURITY_CSP_ENFORCE', true),

    'csp_report_uri' => $cspReportUri,

    'csp_policy' => implode('; ', [
        "default-src 'self'",
        "base-uri 'self'",
        "frame-ancestors 'none'",
        "object-src 'none'",
        "img-src 'self' data:",
        "font-src 'self' data: https://fonts.scalar.com",
        "style-src 'self'",
        "script-src 'self'",
        "connect-src 'self'",
        "form-action 'self'",
        "report-uri {$cspReportUri}",
    ]),

    'csp_docs_policy' => implode('; ', [
        "default-src 'self'",
        "base-uri 'self'",
        "frame-ancestors 'none'",
        "object-src 'none'",
        "img-src 'self' data:",
        "font-src 'self' data: https://fonts.scalar.com",
        "style-src 'self' 'unsafe-inline'",
        "script-src 'self' https://cdn.jsdelivr.net",
        "connect-src 'self'",
        "form-action 'self'",
        "report-uri {$cspReportUri}",
    ]),

    /*
    |--------------------------------------------------------------------------
    | Vulnerability Disclosure (RFC 9116)
    |--------------------------------------------------------------------------
    |
    | Path relative to the repository root. Served at `/.well-known/security.txt`
    | by ShowSecurityTxtController (a route, not static hosting, so test runs
    | and non-nginx servers resolve it the same way).
    |
    */

    'security_txt' => 'public/.well-known/security.txt',

];
