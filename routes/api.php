<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Inline fully-qualified controller class names - no `use` imports at the top
| of this file (see `php-tooling`). Register this file in `bootstrap/app.php`
| without a `->namespace()` on the route group, or Laravel will prepend the
| group namespace to every action and break resolution.
|
*/

/*
|--------------------------------------------------------------------------
| Public Authentication
|--------------------------------------------------------------------------
|
| Credential exchange and account recovery. No bearer token is required;
| every route carries its own dedicated throttle so one surface cannot
| exhaust another's budget.
|
*/

Route::prefix('auth')->middleware(['throttle:api-auth'])->group(function (): void {

    Route::post('/login', \App\Http\Controllers\Auth\LoginController::class)
        ->name('auth.login');

    Route::post('/login/remember', \App\Http\Controllers\Auth\RememberLoginController::class)
        ->name('auth.login.remember');

    Route::post('/register', \App\Http\Controllers\Auth\RegisterController::class)
        ->name('auth.register');

});

/*
|--------------------------------------------------------------------------
| Two-Factor Challenge
|--------------------------------------------------------------------------
|
| Completes the pending challenge opened by login or registration. The
| challenge is resolved from the session cookie or the opaque
| `two_factor_token` returned alongside `two_factor_required`.
|
*/

Route::post('/auth/two-factor/send', \App\Http\Controllers\Auth\SendTwoFactorController::class)
    ->middleware('throttle:api-auth-two-factor-send')
    ->name('two-factor.send');

Route::post('/auth/two-factor/verify', \App\Http\Controllers\Auth\VerifyTwoFactorController::class)
    ->middleware('throttle:api-auth-two-factor-verify')
    ->name('two-factor.verify');

Route::get('/auth/two-factor/status', \App\Http\Controllers\Auth\TwoFactorStatusController::class)
    ->middleware('throttle:api-auth-two-factor-status')
    ->name('two-factor.status');

/*
|--------------------------------------------------------------------------
| Password Reset
|--------------------------------------------------------------------------
|
| Unauthenticated account recovery. Both endpoints answer identically for
| known and unknown addresses; the reset token in the request is the only
| authorisation for setting a new password.
|
*/

Route::post('/auth/forgot-password', \App\Http\Controllers\Auth\ForgotPasswordController::class)
    ->middleware('throttle:api-auth-password')
    ->name('password.request');

Route::post('/auth/reset-password', \App\Http\Controllers\Auth\ResetPasswordController::class)
    ->middleware('throttle:api-auth-password')
    ->name('password.reset');

/*
|--------------------------------------------------------------------------
| Client Credentials
|--------------------------------------------------------------------------
|
| Machine-to-machine token exchange for API Clients. Shorter-lived than a
| Personal Access Token by default; see `client_token_expiration_days`.
|
*/

Route::post('/oauth/token', \App\Http\Controllers\Auth\ClientTokenExchangeController::class)
    ->middleware('throttle:api-client-auth')
    ->name('oauth.token');

/*
|--------------------------------------------------------------------------
| System Status
|--------------------------------------------------------------------------
|
| Public, unauthenticated status page: the current state and daily uptime
| history per monitored component, read from `health:record`'s persisted
| rows. A dedicated per-IP throttle keeps status polling from sharing the
| authenticated API's budget. `GET /health` (routes/web.php) remains the
| load-balancer probe; this endpoint is the human-facing status page.
|
*/

Route::get('/status', \App\Http\Controllers\SystemHealth\SystemStatusController::class)
    ->middleware('throttle:api-status')
    ->name('status');
/*
|--------------------------------------------------------------------------
| Authenticated API
|--------------------------------------------------------------------------
|
| Every route below requires a Sanctum bearer token or a stateful SPA
| session cookie. `active.account` rejects suspended accounts, and
| `session.version` turns away cookies stamped with a superseded version
| (force-logout, password change, password reset).
|
*/

Route::middleware(['auth:sanctum', 'active.account', 'session.version', 'session.touch', 'throttle:api'])->group(function (): void {

    /*
    |--------------------------------------------------------------------------
    | Account
    |--------------------------------------------------------------------------
    |
    | Self-Service Surface: end the current session, view and update the
    | caller's own profile and password.
    |
    */

    Route::post('/logout', \App\Http\Controllers\Auth\LogoutController::class)
        ->name('auth.logout');

    Route::get('/me', \App\Http\Controllers\Users\MeShowController::class)
        ->name('me.show');

    Route::patch('/me', \App\Http\Controllers\Users\UpdateMeController::class)
        ->name('me.update');

    Route::patch('/me/password', \App\Http\Controllers\Users\UpdateMePasswordController::class)
        ->name('me.password.update');

    /*
    |--------------------------------------------------------------------------
    | Sessions
    |--------------------------------------------------------------------------
    |
    | Cookie-bound web session registry - list, revoke one device, or end the
    | current browser without revoking bearer tokens.
    |
    */

    Route::get('/sessions', \App\Http\Controllers\Sessions\SessionIndexController::class)
        ->name('sessions.index');

    Route::delete('/sessions/current', \App\Http\Controllers\Sessions\DestroyCurrentSessionController::class)
        ->name('sessions.current.destroy');

    Route::delete('/sessions/{web_session}', \App\Http\Controllers\Sessions\DestroySessionController::class)
        ->name('sessions.destroy');

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    |
    | Query-Param-Driven User Index and Show Endpoints (`sort`, `fields`,
    | `include`, `filter`, pagination on index) plus Admin Operations:
    | creation, suspension, forced logout, and Admin-Issued Personal Access
    | Tokens for another User.
    |
    */

    Route::get('/users', \App\Http\Controllers\Users\UserIndexController::class)
        ->name('users.index');

    Route::post('/users', \App\Http\Controllers\Users\StoreUserController::class)
        ->name('users.store');

    Route::post('/users/logout', \App\Http\Controllers\Users\ForceLogoutUsersController::class)
        ->name('users.force-logout');

    Route::post('/users/{user}/suspend', \App\Http\Controllers\Users\SuspendUserController::class)
        ->name('users.suspend');

    Route::post('/users/{user}/unsuspend', \App\Http\Controllers\Users\UnsuspendUserController::class)
        ->name('users.unsuspend');

    Route::get('/users/{user}', \App\Http\Controllers\Users\UserShowController::class)
        ->name('users.show');

    Route::patch('/users/{user}', \App\Http\Controllers\Users\UpdateUserController::class)
        ->name('users.update');

    Route::delete('/users/{user}', \App\Http\Controllers\Users\DestroyUserController::class)
        ->name('users.destroy');

    Route::post('/users/{user}/tokens', \App\Http\Controllers\Users\StoreUserTokenController::class)
        ->middleware('throttle:api-tokens')
        ->name('users.tokens.store');

    /*
    |--------------------------------------------------------------------------
    | Personal Access Tokens
    |--------------------------------------------------------------------------
    |
    | Self-Service Tokens for the authenticated caller.
    |
    */

    Route::get('/tokens', \App\Http\Controllers\Tokens\TokenIndexController::class)
        ->name('tokens.index');

    Route::post('/tokens', \App\Http\Controllers\Tokens\StoreTokenController::class)
        ->middleware('throttle:api-tokens')
        ->name('tokens.store');

    Route::delete('/tokens/{token}', \App\Http\Controllers\Tokens\DestroyTokenController::class)
        ->name('tokens.destroy');

    /*
    |--------------------------------------------------------------------------
    | API Clients
    |--------------------------------------------------------------------------
    |
    | Admin-Managed Machine-to-Machine Client Credentials.
    |
    */

    Route::get('/clients', \App\Http\Controllers\Clients\ClientIndexController::class)
        ->name('clients.index');

    Route::post('/clients', \App\Http\Controllers\Clients\StoreClientController::class)
        ->middleware('throttle:api-tokens')
        ->name('clients.store');

    Route::get('/clients/{client}', \App\Http\Controllers\Clients\ClientShowController::class)
        ->name('clients.show');

    Route::patch('/clients/{client}', \App\Http\Controllers\Clients\UpdateClientController::class)
        ->name('clients.update');

    Route::delete('/clients/{client}', \App\Http\Controllers\Clients\DestroyClientController::class)
        ->name('clients.destroy');

    /*
    |--------------------------------------------------------------------------
    | Auth Audit Logs
    |--------------------------------------------------------------------------
    |
    | Admin read-only index of authentication audit events.
    |
    */

    Route::get('/audit-logs', \App\Http\Controllers\AuthAuditLogs\AuthAuditLogIndexController::class)
        ->name('audit-logs.index');

    Route::get('/audit-logs/{auth_audit_log}', \App\Http\Controllers\AuthAuditLogs\AuthAuditLogShowController::class)
        ->name('audit-logs.show');

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    |
    | Query-Param-Driven Role Index Endpoint (`sort`, `fields`, `include`,
    | `filter`, pagination).
    |
    */

    Route::get('/roles', \App\Http\Controllers\Roles\RoleIndexController::class)
        ->name('roles.index');

    Route::get('/roles/{role}', \App\Http\Controllers\Roles\RoleShowController::class)
        ->name('roles.show');

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    |
    | Read-only catalog of registered Spatie permission strings for token and
    | API client ability pickers.
    |
    */

    Route::get('/permissions', \App\Http\Controllers\Permissions\PermissionIndexController::class)
        ->name('permissions.index');

    /*
    |--------------------------------------------------------------------------
    | Teams
    |--------------------------------------------------------------------------
    |
    | Query-Param-Driven Team Index and Show Endpoints (`sort`, `fields`,
    | `filter`, pagination on index).
    |
    */

    Route::get('/teams', \App\Http\Controllers\Teams\TeamIndexController::class)
        ->name('teams.index');

    Route::get('/teams/{team}', \App\Http\Controllers\Teams\TeamShowController::class)
        ->name('teams.show');

});
