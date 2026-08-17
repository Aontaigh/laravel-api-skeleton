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
    | Auth Endpoint Rate Limits
    |--------------------------------------------------------------------------
    |
    | Login and registration each combine a composite email+IP key (stops
    | credential stuffing against one account) with a broader per-IP ceiling
    | (stops distributed spraying across many accounts from one address). A
    | bare per-email counter is deliberately avoided: it would let an attacker
    | lock a victim out simply by hammering their address. The per-IP ceiling
    | is dropped in `local`, where the whole test suite shares one container
    | IP and a hard cap would only lock the developer out.
    |
    */

    'auth_rate_limit_per_minute' => (int) env('API_AUTH_RATE_LIMIT_PER_MINUTE', 5),

    'auth_ip_ceiling_per_minute' => (int) env('API_AUTH_IP_CEILING_PER_MINUTE', 20),

    'two_factor_send_rate_limit_per_minute' => (int) env('API_TWO_FACTOR_SEND_RATE_LIMIT_PER_MINUTE', 5),

    'two_factor_send_ip_ceiling_per_minute' => (int) env('API_TWO_FACTOR_SEND_IP_CEILING_PER_MINUTE', 20),

    'two_factor_verify_rate_limit_per_minute' => (int) env('API_TWO_FACTOR_VERIFY_RATE_LIMIT_PER_MINUTE', 5),

    'two_factor_verify_ip_ceiling_per_minute' => (int) env('API_TWO_FACTOR_VERIFY_IP_CEILING_PER_MINUTE', 20),

    /*
     * Generous allowance for SPA polling while a challenge is pending.
     */
    'two_factor_status_rate_limit_per_minute' => (int) env('API_TWO_FACTOR_STATUS_RATE_LIMIT_PER_MINUTE', 60),

    'client_auth_rate_limit_per_minute' => (int) env('API_CLIENT_AUTH_RATE_LIMIT_PER_MINUTE', 5),

    'client_auth_ip_ceiling_per_minute' => (int) env('API_CLIENT_AUTH_IP_CEILING_PER_MINUTE', 20),

    /*
    |--------------------------------------------------------------------------
    | Client-Credentials Token Lifetime
    |--------------------------------------------------------------------------
    |
    | Number of days until a token issued via POST /oauth/token expires.
    | Shorter than user PAT lifetime by default for machine-to-machine access.
    | Set to 0 to disable expiration (local development only).
    |
    */

    'client_token_expiration_days' => (int) env('API_CLIENT_TOKEN_EXPIRATION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Demo API Client (Seeder)
    |--------------------------------------------------------------------------
    |
    | Plaintext secret for the seeded demo integration client. Override via
    | API_DEMO_CLIENT_SECRET in local environments only.
    |
    */

    'demo_client_secret' => env('API_DEMO_CLIENT_SECRET', 'DemoClientSecret12'),

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
    | Remember-Me Token Lifetime
    |--------------------------------------------------------------------------
    |
    | Number of days until a remember-me Sanctum token expires. Standard login
    | uses token_expiration_days; remember-me extends the PAT lifetime and
    | rotates the User remember_token for cookie-based SPA recall.
    |
    */

    'remember_token_expiration_days' => (int) env('API_REMEMBER_TOKEN_EXPIRATION_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication
    |--------------------------------------------------------------------------
    |
    | Code lifetime, pending-challenge lifetime, and per-code guess budget.
    | Pending TTL should be at least as long as the code TTL so stateless
    | clients can complete send + verify within one window.
    |
    */

    'two_factor_code_ttl_seconds' => (int) env('API_TWO_FACTOR_CODE_TTL_SECONDS', 300),

    'two_factor_pending_ttl_seconds' => (int) env('API_TWO_FACTOR_PENDING_TTL_SECONDS', 300),

    'two_factor_max_attempts' => (int) env('API_TWO_FACTOR_MAX_ATTEMPTS', 5),

    /*
    |--------------------------------------------------------------------------
    | Auth Timing Normalisation Hash
    |--------------------------------------------------------------------------
    |
    | Bcrypt hash used when login email is unknown so Hash::check() cost matches
    | a real password verification. Null is resolved at application boot using
    | the configured bcrypt rounds.
    |
    */

    'auth_timing_normalisation_hash' => env('API_AUTH_TIMING_NORMALISATION_HASH'),

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
