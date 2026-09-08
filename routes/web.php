<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| API Documentation
|--------------------------------------------------------------------------
|
| Scalar interactive reference and the OpenAPI source file. Public in every
| environment - endpoint access still requires a Sanctum bearer token. When
| API_DOCS_BASIC_AUTH_USER and API_DOCS_BASIC_AUTH_PASSWORD are set, both
| routes require HTTP Basic Auth (useful in production).
|
*/

Route::middleware('api-docs')->group(function (): void {
    Route::get('/api/docs', \App\Http\Controllers\Api\ShowApiDocsController::class)
        ->name('api.docs');

    Route::get('/api/openapi.yaml', \App\Http\Controllers\Api\ShowOpenApiSpecController::class)
        ->name('api.openapi');
});

/*
|--------------------------------------------------------------------------
| Health
|--------------------------------------------------------------------------
|
| Public uptime probe - no auth, no throttling. Load balancers and uptime
| monitors hit this to confirm the API is serving and the database answers.
|
*/

Route::get('/health', \App\Http\Controllers\Api\ShowHealthController::class)
    ->middleware('throttle:health')
    ->name('health');

/*
|--------------------------------------------------------------------------
| Vulnerability Disclosure (RFC 9116)
|--------------------------------------------------------------------------
|
| Public security.txt with disclosure contact details. A route (not static
| hosting) so every server and test run resolves it the same way. Forks
| should point Contact at their own disclosure address - see SECURITY.md.
|
*/

Route::get('/.well-known/security.txt', \App\Http\Controllers\WellKnown\ShowSecurityTxtController::class)
    ->name('well-known.security-txt');
