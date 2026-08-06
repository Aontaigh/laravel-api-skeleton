<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API Rate Limits
    |--------------------------------------------------------------------------
    |
    | Per-minute limits keyed by authenticated User id, falling back to IP for
    | unauthenticated callers (health checks and future public routes).
    |
    */

    'rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 500),

    'token_rate_limit_per_minute' => (int) env('API_TOKEN_RATE_LIMIT_PER_MINUTE', 10),

    /*
    |--------------------------------------------------------------------------
    | Personal Access Token Lifetime
    |--------------------------------------------------------------------------
    |
    | Number of days until a newly issued Sanctum token expires. Set to 0 to
    | disable expiration (local development only — not recommended in production).
    | Synced to config/sanctum.php for authentication enforcement.
    |
    */

    'token_expiration_days' => (int) env('API_TOKEN_EXPIRATION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | API Documentation
    |--------------------------------------------------------------------------
    |
    | Scalar serves interactive docs at /api/docs in every environment. Leave
    | both credentials empty for public docs; set both to require HTTP Basic
    | Auth in production (or any environment). The OpenAPI file path is relative
    | to the application base path.
    |
    */

    'docs_basic_auth' => [
        'user' => env('API_DOCS_BASIC_AUTH_USER'),
        'password' => env('API_DOCS_BASIC_AUTH_PASSWORD'),
    ],

    'openapi_spec' => 'docs/openapi.yaml',

];
