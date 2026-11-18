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
| Scalar interactive reference and the OpenAPI source file. Both require an
| authenticated principal: the specification enumerates every path, field and
| error code, so publishing it anonymously hands an attacker the reconnaissance
| work ASVS 13.4.5 and OWASP API9:2023 expect the application to withhold.
|
| `auth:sanctum` rather than `auth` so a session cookie, a first-party bearer
| token, and a tooling token all work. This application is API-only, so a guest
| receives the 401 envelope rather than a redirect (`redirectGuestsTo` answers
| null for every path).
|
*/

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
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
