<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\RoleName;
use App\Events\AuthEventOccurred;
use App\Events\TwoFactorChallengeIssued;
use App\Listeners\RecordAuthAuditLog;
use App\Listeners\SendTwoFactorCodeNotification;
use App\Models\ApiClient;
use App\Models\AuthAuditLog;
use App\Models\User;
use App\Policies\ApiClientPolicy;
use App\Policies\AuthAuditLogPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\PersonalAccessTokenPolicy;
use App\Policies\RolePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Permission;
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
        Gate::policy(ApiClient::class, ApiClientPolicy::class);
        Gate::policy(AuthAuditLog::class, AuthAuditLogPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);

        Event::listen(AuthEventOccurred::class, RecordAuthAuditLog::class);
        Event::listen(TwoFactorChallengeIssued::class, SendTwoFactorCodeNotification::class);

        $this->registerTelescopeGate();
        $this->configurePasswordDefaults();
        $this->configureApiRateLimiting();
        $this->configureAuthTimingNormalisation();
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

        RateLimiter::for('api-auth', function (Request $request): array {
            return [
                Limit::perMinute(config()->integer('api.auth_rate_limit_per_minute'))
                    ->by($this->authCompositeKey($request, 'email')),
                ...$this->perIpCeiling(config()->integer('api.auth_ip_ceiling_per_minute'), $request),
            ];
        });

        RateLimiter::for('api-client-auth', function (Request $request): array {
            return [
                Limit::perMinute(config()->integer('api.client_auth_rate_limit_per_minute'))
                    ->by($this->authCompositeKey($request, 'client_id')),
                ...$this->perIpCeiling(config()->integer('api.client_auth_ip_ceiling_per_minute'), $request),
            ];
        });
    }

    /**
     * Build a composite rate-limit key from a normalised credential field and the IP.
     *
     * @param  Request $request the incoming request
     * @param  string  $field   the credential field name (`email` or `client_id`)
     * @return string  the `field|ip` limiter key
     */
    private function authCompositeKey(Request $request, string $field): string
    {
        $value = $request->string($field, '')->lower()->toString();

        return $value.'|'.$request->ip();
    }

    /**
     * Build the broad per-IP ceiling that backs each auth limiter.
     *
     * This ceiling is a shared-network safeguard: it stops one IP from hammering
     * many different accounts. Locally every request — including the whole test
     * suite — originates from a single container IP, so the ceiling only locks
     * the developer out while adding nothing. It is therefore dropped in the
     * `local` environment; the per-credential composite limits (which carry the
     * real anti-abuse intent) always remain.
     *
     * @param  int         $perMinute the per-IP allowance for this endpoint
     * @param  Request     $request   the incoming request
     * @return list<Limit> the per-IP limit, or an empty list in local
     */
    private function perIpCeiling(int $perMinute, Request $request): array
    {
        if ($this->app->environment('local')) {
            return [];
        }

        return [Limit::perMinute($perMinute)->by((string) $request->ip())];
    }

    /**
     * Define the application-wide default password policy.
     *
     * Applied wherever a FormRequest uses `Password::defaults()` — currently
     * registration. Min 12 characters, letters, mixed case, numbers, and a
     * HaveIBeenPwned breach check (`uncompromised()`).
     */
    private function configurePasswordDefaults(): void
    {
        Password::defaults(static fn (): Password => Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->uncompromised());
    }

    /**
     * Resolve the timing-normalisation hash when not set in config.
     */
    private function configureAuthTimingNormalisation(): void
    {
        if (config('api.auth_timing_normalisation_hash') !== null) {
            return;
        }

        config(['api.auth_timing_normalisation_hash' => Hash::make('auth-timing-normalisation')]);
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
