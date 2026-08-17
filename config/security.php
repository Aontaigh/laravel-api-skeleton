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
    | Two policies: a strict default for JSON API responses, and a relaxed
    | docs policy for the Scalar page at `GET /api/docs` which loads its bundle
    | and inline bootstrap from jsDelivr. Set SECURITY_CSP_ENFORCE=false to fall
    | back to report-only while iterating. In `local`, CSP is omitted entirely
    | while Vite hot-reload is active.
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
        "style-src 'self'",
        "script-src 'self'",
        "connect-src 'self'",
        "form-action 'self'",
    ]),

    'csp_docs_policy' => implode('; ', [
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
