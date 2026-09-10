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
    | Public System Status Rate Limit
    |--------------------------------------------------------------------------
    |
    | GET /status is a public, unauthenticated status page, so the limiter is
    | keyed per IP only - there is no authenticated User to key on. A dedicated
    | key keeps status-page polling from exhausting the authenticated API's
    | budget (and vice versa).
    |
    */

    'status_rate_limit_per_minute' => (int) env('API_STATUS_RATE_LIMIT_PER_MINUTE', 30),

    'health_rate_limit_per_minute' => (int) env('API_HEALTH_RATE_LIMIT_PER_MINUTE', 60),

    /*
    |--------------------------------------------------------------------------
    | Auth Endpoint Rate Limits
    |--------------------------------------------------------------------------
    |
    | Login and registration each combine a composite email+IP key (stops
    | credential stuffing against one account) with a broader per-IP ceiling
    | (stops distributed spraying across many accounts from one address). The
    | ceilings are split: registration is cheaper to abuse for account-creation
    | spam (tighter ceiling), while login needs headroom for a NAT full of
    | legitimate users. A bare per-email counter is deliberately avoided: it
    | would let an attacker lock a victim out simply by hammering their address.
    | The per-IP ceiling is dropped in `local`, where the whole test suite
    | shares one container IP and a hard cap would only lock the developer out.
    |
    */

    'auth_rate_limit_per_minute' => (int) env('API_AUTH_RATE_LIMIT_PER_MINUTE', 5),

    'auth_login_ip_ceiling_per_minute' => (int) env('API_AUTH_LOGIN_IP_CEILING_PER_MINUTE', 20),

    'auth_register_ip_ceiling_per_minute' => (int) env('API_AUTH_REGISTER_IP_CEILING_PER_MINUTE', 10),

    /*
    |--------------------------------------------------------------------------
    | Authenticated Credential Rate Limits
    |--------------------------------------------------------------------------
    |
    | Password change and session revokes key on User ID + IP rather than
    | email: these requests carry no email field, so an email-keyed limiter
    | would collapse every caller on an IP into one bucket. The old shared
    | `auth_ip_ceiling_per_minute` key is removed - every consumer now names
    | its own ceiling below.
    |
    */

    'auth_password_ip_ceiling_per_minute' => (int) env('API_AUTH_PASSWORD_IP_CEILING_PER_MINUTE', 15),

    'two_factor_send_rate_limit_per_minute' => (int) env('API_TWO_FACTOR_SEND_RATE_LIMIT_PER_MINUTE', 5),

    'two_factor_send_ip_ceiling_per_minute' => (int) env('API_TWO_FACTOR_SEND_IP_CEILING_PER_MINUTE', 20),

    'two_factor_verify_rate_limit_per_minute' => (int) env('API_TWO_FACTOR_VERIFY_RATE_LIMIT_PER_MINUTE', 5),

    'two_factor_verify_ip_ceiling_per_minute' => (int) env('API_TWO_FACTOR_VERIFY_IP_CEILING_PER_MINUTE', 20),

    /*
     * Generous allowance for SPA polling while a challenge is pending, with a
     * broad per-IP ceiling so one chatty client cannot starve polling for
     * everyone else behind the same address.
     */
    'two_factor_status_rate_limit_per_minute' => (int) env('API_TWO_FACTOR_STATUS_RATE_LIMIT_PER_MINUTE', 60),

    'two_factor_status_ip_ceiling_per_minute' => (int) env('API_TWO_FACTOR_STATUS_IP_CEILING_PER_MINUTE', 60),

    'client_auth_rate_limit_per_minute' => (int) env('API_CLIENT_AUTH_RATE_LIMIT_PER_MINUTE', 5),

    'client_auth_ip_ceiling_per_minute' => (int) env('API_CLIENT_AUTH_IP_CEILING_PER_MINUTE', 20),

    /*
    |--------------------------------------------------------------------------
    | Password Reset Rate Limits
    |--------------------------------------------------------------------------
    |
    | Forgot-password and reset-password share one limiter. A composite
    | email+IP key stops link flooding against one account; the broad per-IP
    | ceiling (dropped in `local`) stops distributed spraying across many
    | addresses from one host.
    |
    */

    'password_reset_rate_limit_per_minute' => (int) env('API_PASSWORD_RESET_RATE_LIMIT_PER_MINUTE', 5),

    'password_reset_ip_ceiling_per_minute' => (int) env('API_PASSWORD_RESET_IP_CEILING_PER_MINUTE', 20),

    /*
    |--------------------------------------------------------------------------
    | E-Mail Verification Rate Limits
    |--------------------------------------------------------------------------
    |
    | The signed verify link is unforgeable, so `email-verify` only bounds
    | lookup abuse with a per-IP ceiling (dropped in `local`). Resend is
    | authenticated and keyed on the User ID + IP.
    |
    */

    'email_verify_ip_ceiling_per_minute' => (int) env('API_EMAIL_VERIFY_IP_CEILING_PER_MINUTE', 15),

    'email_verification_rate_limit_per_minute' => (int) env('API_EMAIL_VERIFICATION_RATE_LIMIT_PER_MINUTE', 3),

    /*
    |--------------------------------------------------------------------------
    | CSP Report Rate Limit
    |--------------------------------------------------------------------------
    |
    | Browser violation reports are anonymous and bursty (one misconfigured
    | page fires dozens at once), so the per-IP ceiling is generous. Dropped
    | in `local` like the other public ceilings.
    |
    */

    'csp_report_ip_ceiling_per_minute' => (int) env('API_CSP_REPORT_IP_CEILING_PER_MINUTE', 60),

    /*
    |--------------------------------------------------------------------------
    | Outbound Webhook Delivery
    |--------------------------------------------------------------------------
    |
    | Retry budget, per-attempt HTTP timeout, and the consecutive-failure
    | streak that auto-disables a dead endpoint. Backoff between attempts is
    | exponential from 60 seconds, capped at one hour.
    |
    */

    'webhook_delivery_max_attempts' => (int) env('API_WEBHOOK_DELIVERY_MAX_ATTEMPTS', 8),

    'webhook_delivery_timeout_seconds' => (int) env('API_WEBHOOK_DELIVERY_TIMEOUT_SECONDS', 10),

    'webhook_auto_disable_after_failures' => (int) env('API_WEBHOOK_AUTO_DISABLE_AFTER_FAILURES', 10),

    'webhook_rate_limit_per_minute' => (int) env('API_WEBHOOK_RATE_LIMIT_PER_MINUTE', 10),

    /*
    |--------------------------------------------------------------------------
    | Password Length Cap
    |--------------------------------------------------------------------------
    |
    | Maximum accepted length for password fields. Enforced before hash
    | verification so long inputs cannot be abused for CPU exhaustion.
    |
    */

    'password_max_length' => (int) env('API_PASSWORD_MAX_LENGTH', 255),

    'email_max_length' => (int) env('API_EMAIL_MAX_LENGTH', 255),

    /*
     * Laravel issues 64-character password-reset tokens. Bound the raw token
     * before hash comparison; the database column stores the hash, not the token.
     */
    'password_reset_token_max_length' => (int) env('API_PASSWORD_RESET_TOKEN_MAX_LENGTH', 64),

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
    | disable expiration (local development only - not recommended in production).
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
    | Password hash compared against when the login email is unknown, so the
    | Hash::check() cost on a miss matches a real verification and response
    | timing does not reveal whether the account exists. Null is resolved at
    | application boot using the configured hash driver (Argon2id) and factors.
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

    /*
    |--------------------------------------------------------------------------
    | Password Reset E-Mail Destinations
    |--------------------------------------------------------------------------
    |
    | The API only serves JSON, so reset links point at a separate frontend.
    | Set both to the SPA's absolute page URLs. Empty values fall back to
    | `url('/reset-password')` and `url('/forgot-password')` on the API host,
    | which is only useful in local development.
    |
    */

    'password_reset_url' => env('API_PASSWORD_RESET_URL'),

    /*
    | The SPA page showing the e-mail verification outcome. The verify
    | controller redirects the browser here with `?verified=1|0`. Empty
    | falls back to `url('/verify-email')` on the API host, which is only
    | useful in local development.
    |
    */

    'email_verification_url' => env('API_EMAIL_VERIFICATION_URL'),

    'password_forgot_url' => env('API_PASSWORD_FORGOT_URL'),

    'docs_basic_auth' => [
        'user' => env('API_DOCS_BASIC_AUTH_USER'),
        'password' => env('API_DOCS_BASIC_AUTH_PASSWORD'),
    ],

    'openapi_spec' => 'docs/openapi.yaml',

];
