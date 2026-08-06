<?php

declare(strict_types=1);

use App\Support\CommaSeparatedList;

$configuredOrigins = CommaSeparatedList::parse((string) env('CORS_ALLOWED_ORIGINS', ''));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Browser-based frontends need CORS headers when they call this API from a
    | they call this API from a different origin. Bearer-token requests do not
    | require supports_credentials; set CORS_SUPPORTS_CREDENTIALS=true only for
    | Sanctum cookie / CSRF SPA auth (see SANCTUM_STATEFUL_DOMAINS).
    |
    | Set CORS_ALLOWED_ORIGINS to a comma-separated list in production, e.g.
    | https://app.example.com,https://www.example.com. When unset, local and
    | testing environments allow common local dev-server origins.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $configuredOrigins !== []
        ? $configuredOrigins
        : match ((string) env('APP_ENV', 'production')) {
            'local', 'testing' => [
                'http://localhost:3000',
                'http://localhost:5173',
                'http://127.0.0.1:3000',
                'http://127.0.0.1:5173',
            ],
            default => [],
        },

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => (int) env('CORS_MAX_AGE', 3600),

    'supports_credentials' => filter_var(env('CORS_SUPPORTS_CREDENTIALS', false), FILTER_VALIDATE_BOOL),

];
