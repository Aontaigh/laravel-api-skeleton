<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Inline fully-qualified controller class names — no `use` imports at the top
| of this file (see `php-tooling`). Register this file in `bootstrap/app.php`
| without a `->namespace()` on the route group, or Laravel will prepend the
| group namespace to every action and break resolution.
|
*/

Route::middleware(['throttle:api-auth'])->group(function (): void {

    Route::post('/login', \App\Http\Controllers\Auth\LoginController::class)
        ->name('auth.login');

    Route::post('/login/remember', \App\Http\Controllers\Auth\RememberLoginController::class)
        ->name('auth.login.remember');

    Route::post('/register', \App\Http\Controllers\Auth\RegisterController::class)
        ->name('auth.register');

    Route::post('/oauth/token', \App\Http\Controllers\Auth\ClientTokenExchangeController::class)
        ->middleware('throttle:api-client-auth')
        ->name('oauth.token');

});

Route::middleware(['auth:sanctum', 'active.account', 'session.version', 'throttle:api'])->group(function (): void {

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
    | Users
    |--------------------------------------------------------------------------
    |
    | Query-Param-Driven User Index and Show Endpoints (`sort`, `fields`,
    | `include`, `filter`, pagination on index) plus admin-issued Personal
    | Access Tokens for another User.
    |
    */

    Route::get('/users', \App\Http\Controllers\Users\UserIndexController::class)
        ->name('users.index');

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
    | Tokens
    |--------------------------------------------------------------------------
    |
    | Self-Service Personal Access Tokens for the authenticated caller.
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
    | Admin-managed machine-to-machine client credentials.
    |
    */

    Route::get('/clients', \App\Http\Controllers\Clients\ClientIndexController::class)
        ->name('clients.index');

    Route::post('/clients', \App\Http\Controllers\Clients\StoreClientController::class)
        ->middleware('throttle:api-tokens')
        ->name('clients.store');

    Route::get('/clients/{client}', \App\Http\Controllers\Clients\ClientShowController::class)
        ->name('clients.show');

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
