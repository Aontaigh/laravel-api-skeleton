<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security
    |--------------------------------------------------------------------------
    |
    | Max-age (in seconds) advertised via the Strict-Transport-Security header
    | on secure responses. One year with subdomain + preload is the standard.
    | Sent only in production/staging or over TLS — plain-http local dev skips it.
    |
    */

    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | The CSP is enforced by default. `style-src` allows `'unsafe-inline'` for
    | inline styles; `script-src` allows `'unsafe-inline'` and the jsDelivr CDN
    | because the Scalar API-docs page loads its bundle and an inline bootstrap
    | from there. The rest of the app serves JSON, so the policy primarily
    | protects the HTML docs and welcome pages. Set SECURITY_CSP_ENFORCE=false
    | to fall back to report-only while iterating.
    |
    */

    'csp_enforce' => (bool) env('SECURITY_CSP_ENFORCE', true),

    'csp_policy' => implode('; ', [
        "default-src 'self'",
        "base-uri 'self'",
        "frame-ancestors 'none'",
        "object-src 'none'",
        "img-src 'self' data:",
        "font-src 'self' data:",
        "style-src 'self' 'unsafe-inline'",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
        "connect-src 'self'",
        "form-action 'self'",
    ]),

];
