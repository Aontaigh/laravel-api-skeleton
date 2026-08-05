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

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {

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

});
