<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\RoleName;
use App\Models\User;
use App\Policies\PersonalAccessTokenPolicy;
use App\Policies\RolePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;

/**
 * Bootstraps application-wide services and policy registrations.
 */
final class AppServiceProvider extends ServiceProvider
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Register any application services.
     *
     * Telescope is registered here, not in `bootstrap/providers.php`, so it
     * never loads outside `local` — it is a development-only dependency and
     * has no business booting routes, migrations, or its dashboard in a
     * deployed environment.
     */
    public function register(): void
    {
        if ($this->app->environment('local')) {
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * `PersonalAccessToken` and Spatie's `Role` live outside `App\Models`, so
     * Laravel's convention-based policy discovery cannot find their Policies —
     * register them explicitly.
     */
    public function boot(): void
    {
        Gate::policy(PersonalAccessToken::class, PersonalAccessTokenPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        $this->registerTelescopeGate();
        $this->configureApiRateLimiting();
        $this->registerScopedTokenBinding();
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Register the Telescope dashboard gate.
     */
    private function registerTelescopeGate(): void
    {
        Gate::define('viewTelescope', static function (User $user): bool {
            return $user->hasRole(RoleName::Admin->value);
        });
    }

    /**
     * Configure per-minute API rate limits.
     */
    private function configureApiRateLimiting(): void
    {
        RateLimiter::for('api', static function (Request $request) {
            $user = $request->user();

            return Limit::perMinute(config()->integer('api.rate_limit_per_minute'))
                ->by($user !== null ? (string) $user->id : $request->ip());
        });

        RateLimiter::for('api-tokens', static function (Request $request) {
            $user = $request->user();

            return Limit::perMinute(config()->integer('api.token_rate_limit_per_minute'))
                ->by($user !== null ? (string) $user->id : $request->ip());
        });
    }

    /**
     * Resolve `{token}` only within the authenticated User's own tokens.
     *
     * Foreign ids return 404 so callers cannot probe whether a token exists.
     */
    private function registerScopedTokenBinding(): void
    {
        Route::bind('token', static function (string $value): PersonalAccessToken {
            /** @var User|null $user */
            $user = auth()->user();

            if ($user === null) {
                throw (new ModelNotFoundException)->setModel(PersonalAccessToken::class, [$value]);
            }

            return $user->tokens()->whereKey($value)->firstOrFail();
        });
    }
}
