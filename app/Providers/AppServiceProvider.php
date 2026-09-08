<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\GeoIp\GeoIpLocator;
use App\Enums\RoleName;
use App\Models\ApiClient;
use App\Models\AuthAuditLog;
use App\Models\User;
use App\Models\WebSession;
use App\Policies\ApiClientPolicy;
use App\Policies\AuthAuditLogPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\PersonalAccessTokenPolicy;
use App\Policies\RolePolicy;
use App\Policies\WebSessionPolicy;
use App\Services\GeoIp\GeoIpDatabase;
use App\Services\GeoIp\MaxMindGeoIpLocator;
use App\Services\UserAgent\Contracts\UserAgentParser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
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
     * never loads outside `local`: it is a development-only dependency and
     * has no business booting routes, migrations, or its dashboard in a
     * deployed environment.
     *
     * @return void
     */
    public function register(): void
    {
        if ($this->app->environment('local')) {
            $this->app->register(TelescopeServiceProvider::class);
        }

        $this->registerUserAgentParser();
        $this->registerGeoIp();
    }

    /**
     * Bind the configured user-agent parser driver to the UserAgentParser contract.
     *
     * The driver key and its implementation map live in config/useragent.php, so
     * swapping parsers is a one-line config change with no provider edit.
     *
     * @return void
     */
    private function registerUserAgentParser(): void
    {
        $this->app->bind(UserAgentParser::class, function (): UserAgentParser {
            /** @var array<string, class-string<UserAgentParser>> $drivers */
            $drivers = config()->array('useragent.drivers');

            /** @var class-string<UserAgentParser> $implementation */
            $implementation = $drivers[config()->string('useragent.driver')] ?? $drivers['basic'];

            return new $implementation;
        });
    }

    /**
     * Bind the GeoLite2 database and the fail-open locator.
     *
     * The database is a process-lifetime singleton: the MMDB is immutable,
     * and remapping it per request would stall every lookup. The locator is
     * bound per resolution so no lookup state can survive across requests.
     *
     * @return void
     */
    private function registerGeoIp(): void
    {
        $this->app->singleton(GeoIpDatabase::class);
        $this->app->bind(GeoIpLocator::class, MaxMindGeoIpLocator::class);
    }

    /**
     * Bootstrap any application services.
     *
     * `PersonalAccessToken` and Spatie's `Role` live outside `App\Models`, so
     * Laravel's convention-based policy discovery cannot find their Policies,
     * register them explicitly.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->rejectReflectiveCors();
        Gate::policy(PersonalAccessToken::class, PersonalAccessTokenPolicy::class);
        Gate::policy(ApiClient::class, ApiClientPolicy::class);
        Gate::policy(AuthAuditLog::class, AuthAuditLogPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(WebSession::class, WebSessionPolicy::class);

        $this->registerTelescopeGate();
        $this->configurePasswordDefaults();
        $this->configureApiRateLimiting();
        $this->configureAuthTimingNormalisation();
        $this->registerScopedTokenBinding();
        $this->registerScopedWebSessionBinding();
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Register the Telescope dashboard gate.
     *
     * @return void
     */
    private function registerTelescopeGate(): void
    {
        Gate::define('viewTelescope', static function (User $user): bool {
            return $user->hasRole(RoleName::Admin->value);
        });
    }

    /**
     * Configure per-minute API rate limits.
     *
     * @return void
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

        RateLimiter::for('api-auth-two-factor-send', function (Request $request): array {
            return [
                Limit::perMinute(config()->integer('api.two_factor_send_rate_limit_per_minute'))
                    ->by($this->twoFactorCompositeKey($request)),
                ...$this->perIpCeiling(config()->integer('api.two_factor_send_ip_ceiling_per_minute'), $request),
            ];
        });

        RateLimiter::for('api-auth-two-factor-verify', function (Request $request): array {
            return [
                Limit::perMinute(config()->integer('api.two_factor_verify_rate_limit_per_minute'))
                    ->by($this->twoFactorCompositeKey($request)),
                ...$this->perIpCeiling(config()->integer('api.two_factor_verify_ip_ceiling_per_minute'), $request),
            ];
        });

        RateLimiter::for('api-auth-two-factor-status', function (Request $request): array {
            return [
                Limit::perMinute(config()->integer('api.two_factor_status_rate_limit_per_minute'))
                    ->by($this->twoFactorCompositeKey($request)),
            ];
        });

        /*
         * Public System Status page: per-IP only - there is no authenticated
         * User to key on. A dedicated limiter key keeps status polling from
         * sharing (or exhausting) the authenticated `api` budget.
         */

        RateLimiter::for('api-status', static function (Request $request): array {
            return [
                Limit::perMinute(config()->integer('api.status_rate_limit_per_minute'))
                    ->by((string) $request->ip()),
            ];
        });

        RateLimiter::for('health', static function (Request $request): array {
            return [
                Limit::perMinute(config()->integer('api.health_rate_limit_per_minute'))
                    ->by((string) $request->ip()),
            ];
        });

        RateLimiter::for('api-auth-password', function (Request $request): array {
            return [
                Limit::perMinute(config()->integer('api.password_reset_rate_limit_per_minute'))
                    ->by($this->authCompositeKey($request, 'email')),
                ...$this->perIpCeiling(config()->integer('api.password_reset_ip_ceiling_per_minute'), $request),
            ];
        });

        /*
         * The signed e-mail-verification link. The signature already makes it
         * unforgeable, so this only bounds lookup abuse: a per-IP ceiling
         * (dropped in `local`), generous enough for a shared NAT.
         */
        RateLimiter::for('email-verify', fn (Request $request): array => $this->perIpCeiling(
            config()->integer('api.email_verify_ip_ceiling_per_minute'),
            $request,
        ));

        /*
         * Verification resend is authenticated and takes no input, so it keys
         * on the current User ID + IP.
         */
        RateLimiter::for('auth-verification', function (Request $request): array {
            $identifier = $request->user()?->getAuthIdentifier();
            $key = (is_scalar($identifier) ? (string) $identifier : (string) $request->ip()).'|'.$request->ip();

            return [Limit::perMinute(config()->integer('api.email_verification_rate_limit_per_minute'))->by($key)];
        });

        /*
         * Browser CSP violation reports. The caller is anonymous and a single
         * misconfigured page can burst dozens of reports at once, so the
         * ceiling is more generous than the other public endpoints. Per IP
         * in every environment except `local`, like the other public limiters.
         */
        RateLimiter::for('csp-reports', fn (Request $request): array => $this->perIpCeiling(
            config()->integer('api.csp_report_ip_ceiling_per_minute'),
            $request,
        ));
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
     * Build a composite rate-limit key for two-factor endpoints.
     *
     * Stateless clients pass `two_factor_token`; session clients fall back to the
     * session ID so resend and verify budgets stay scoped to one challenge.
     *
     * @param  Request $request the inbound HTTP request
     * @return string  the composite limiter key
     */
    private function twoFactorCompositeKey(Request $request): string
    {
        $token = $request->string('two_factor_token', '')->toString();

        if ($token !== '') {
            return hash('sha256', $token).'|'.$request->ip();
        }

        if ($request->hasSession()) {
            return $request->session()->getId().'|'.$request->ip();
        }

        return 'anonymous|'.$request->ip();
    }

    /**
     * Build the broad per-IP ceiling that backs each auth limiter.
     *
     * This ceiling is a shared-network safeguard: it stops one IP from hammering
     * many different accounts. Locally every request, including the whole
     * test suite, originates from a single container IP, so the ceiling only locks
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
     * Applied wherever a FormRequest uses `Password::defaults()`, currently
     * registration. Min 12 characters, letters, mixed case, numbers, and a
     * HaveIBeenPwned breach check (`uncompromised()`).
     *
     * @return void
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
     * Refuse a CORS configuration that reflects any origin while allowing
     * credentials: the vendored CORS service would then echo arbitrary origins
     * with `Access-Control-Allow-Credentials: true`, letting any site read
     * cookie-authenticated responses. Fail at boot, not at first exploit.
     *
     * @return void
     */
    private function rejectReflectiveCors(): void
    {
        $origins = config()->array('cors.allowed_origins');
        $credentials = config()->boolean('cors.supports_credentials');

        if ($credentials && in_array('*', $origins, true)) {
            throw new InvalidArgumentException(
                'CORS_ALLOWED_ORIGINS Must Not Contain * While CORS_SUPPORTS_CREDENTIALS Is Enabled',
            );
        }
    }

    /**
     * Resolve the timing-normalisation hash when not set in config.
     *
     * @return void
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
     * Foreign IDs return 404 so callers cannot probe whether a token exists.
     *
     * @return void
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

    /**
     * Resolve `{web_session}` within the caller's row scope.
     *
     * Callers holding `sessions.list-all` or `sessions.revoke-any` may address
     * any registry row; everyone else is scoped to their own User ID so foreign
     * IDs return 404. The `revoke-any` branch keeps the binding consistent with
     * WebSessionPolicy::delete, which grants cross-user revoke via `revoke-any`.
     *
     * @return void
     */
    private function registerScopedWebSessionBinding(): void
    {
        Route::bind('web_session', static function (string $value): WebSession {
            /** @var User|null $user */
            $user = auth()->user();

            if ($user === null) {
                throw (new ModelNotFoundException)->setModel(WebSession::class, [$value]);
            }

            $query = WebSession::query()->whereKey($value);

            if (! $user->can('sessions.list-all') && ! $user->can('sessions.revoke-any')) {
                $query->where('user_id', $user->id);
            }

            return $query->firstOrFail();
        });
    }
}
