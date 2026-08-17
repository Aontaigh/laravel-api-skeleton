<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Actions\Auth\IssueTwoFactorChallengeAction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Holds the pending two-factor challenge between the credential step and
 * verification. Stateful clients use the session; stateless clients pass the
 * opaque {@see begin()} token on send, verify, and status.
 */
final class PendingTwoFactor
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * Session key holding the pending challenge's User id.
     */
    public const string USER_ID_KEY = 'auth.two_factor.user_id';

    /**
     * Session key holding whether remember-me was requested at login.
     */
    public const string REMEMBER_KEY = 'auth.two_factor.remember';

    /**
     * Session key holding the opaque token returned to stateless clients.
     */
    public const string TOKEN_KEY = 'auth.two_factor.token';

    /**
     * Session key holding the device label submitted at login/register.
     */
    public const string DEVICE_NAME_KEY = 'auth.two_factor.device_name';

    /**
     * Session key holding when the pending challenge expires.
     */
    public const string EXPIRES_AT_KEY = 'auth.two_factor.expires_at';

    /**
     * Cache key prefix for pending challenge payloads keyed by opaque token.
     */
    public const string PENDING_CACHE_PREFIX = 'two-factor-pending:';

    /**
     * Cache key prefix mapping a User id to their current opaque pending token.
     */
    public const string PENDING_USER_PREFIX = 'two-factor-pending-user:';

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Open a fresh pending challenge and return an opaque token for stateless clients.
     *
     * Clears any prior code challenge so a new login always starts with a full
     * attempt budget.
     *
     * @param  int         $userId         the User awaiting two-factor verification
     * @param  bool        $shouldRemember whether to issue a remember-me token on success
     * @param  string|null $deviceName     the device label submitted at login/register
     * @return string      the opaque token for stateless send/verify requests
     */
    public static function begin(int $userId, bool $shouldRemember, ?string $deviceName = null): string
    {
        /** @var User|null $user */
        $user = User::query()->find($userId);

        if ($user instanceof User) {
            Cache::forget(IssueTwoFactorChallengeAction::cacheKey($user));
        }

        $previousToken = Cache::get(self::pendingUserCacheKey($userId));

        if (is_string($previousToken)) {
            Cache::forget(self::pendingCacheKey($previousToken));
        }

        $token = Str::random(40);
        $expiresAt = now()->addSeconds(config()->integer('api.two_factor_pending_ttl_seconds'))->timestamp;

        session()->put(self::USER_ID_KEY, $userId);
        session()->put(self::REMEMBER_KEY, $shouldRemember);
        session()->put(self::TOKEN_KEY, $token);
        session()->put(self::EXPIRES_AT_KEY, $expiresAt);

        if ($deviceName !== null) {
            session()->put(self::DEVICE_NAME_KEY, $deviceName);
        }

        Cache::put(
            self::pendingCacheKey($token),
            [
                'user_id' => $userId,
                'remember' => $shouldRemember,
                'device_name' => $deviceName,
                'expires_at' => $expiresAt,
            ],
            config()->integer('api.two_factor_pending_ttl_seconds'),
        );

        Cache::put(
            self::pendingUserCacheKey($userId),
            $token,
            config()->integer('api.two_factor_pending_ttl_seconds'),
        );

        return $token;
    }

    /**
     * Resolve the pending challenge from the session or an opaque token.
     *
     * @param  string|null                    $token optional token from send/verify when no session cookie is used
     * @return PendingTwoFactorChallenge|null the pending challenge, or null when none is valid
     */
    public static function resolve(?string $token = null): ?PendingTwoFactorChallenge
    {
        $sessionUserId = self::userId();

        if ($sessionUserId !== null) {
            $sessionToken = session()->get(self::TOKEN_KEY);
            $sessionDeviceName = session()->get(self::DEVICE_NAME_KEY);

            return new PendingTwoFactorChallenge(
                userId: $sessionUserId,
                shouldRemember: self::shouldRemember(),
                deviceName: is_string($sessionDeviceName) ? $sessionDeviceName : null,
                token: is_string($sessionToken) ? $sessionToken : $token,
            );
        }

        if ($token === null || $token === '') {
            return null;
        }

        $payload = Cache::get(self::pendingCacheKey($token));

        if (! is_array($payload) || ! is_int($payload['user_id'] ?? null)) {
            return null;
        }

        if (Cache::get(self::pendingUserCacheKey($payload['user_id'])) !== $token) {
            return null;
        }

        return new PendingTwoFactorChallenge(
            userId: $payload['user_id'],
            shouldRemember: (bool) ($payload['remember'] ?? false),
            deviceName: is_string($payload['device_name'] ?? null) ? $payload['device_name'] : null,
            token: $token,
        );
    }

    /**
     * Read the pending challenge expiry as a Unix timestamp.
     *
     * Session-bound clients store the expiry in the session payload; stateless
     * clients rely on the opaque-token cache. When a session expiry stamp is
     * present it always wins — even if a caller also passes an opaque token —
     * so polling and send/verify stay aligned with the active browser session.
     *
     * @param  string|null $token optional token from status/send/verify when no session cookie is used
     * @return int|null    the expiry timestamp, or null when no pending challenge exists
     */
    public static function expiresAt(?string $token = null): ?int
    {
        $sessionExpiresAt = session()->get(self::EXPIRES_AT_KEY);

        if (is_numeric($sessionExpiresAt)) {
            return (int) $sessionExpiresAt;
        }

        if ($token === null || $token === '') {
            $sessionToken = session()->get(self::TOKEN_KEY);

            if (is_string($sessionToken)) {
                $token = $sessionToken;
            }
        }

        if ($token === null || $token === '') {
            return null;
        }

        $payload = Cache::get(self::pendingCacheKey($token));

        if (! is_array($payload) || ! is_numeric($payload['expires_at'] ?? null)) {
            return null;
        }

        return (int) $payload['expires_at'];
    }

    /**
     * Get the pending challenge's User id, or null when none is pending.
     *
     * @return int|null the pending User id
     */
    public static function userId(): ?int
    {
        $value = session()->get(self::USER_ID_KEY);

        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && is_numeric($value) ? (int) $value : null;
    }

    /**
     * Determine whether remember-me was requested for the pending challenge.
     *
     * @return bool true when remember-me should be applied
     */
    public static function shouldRemember(): bool
    {
        return (bool) session()->get(self::REMEMBER_KEY, false);
    }

    /**
     * Clear the pending challenge from the session and cache.
     *
     * @param string|null $token optional explicit token when the session is unavailable
     */
    public static function forget(?string $token = null): void
    {
        if ($token === null) {
            $sessionToken = session()->get(self::TOKEN_KEY);

            if (is_string($sessionToken)) {
                $token = $sessionToken;
            }
        }

        if (is_string($token)) {
            $payload = Cache::get(self::pendingCacheKey($token));

            if (is_array($payload) && is_int($payload['user_id'] ?? null)) {
                $userKey = self::pendingUserCacheKey($payload['user_id']);

                if (Cache::get($userKey) === $token) {
                    Cache::forget($userKey);
                }
            }

            Cache::forget(self::pendingCacheKey($token));
        }

        session()->forget([
            self::USER_ID_KEY,
            self::REMEMBER_KEY,
            self::TOKEN_KEY,
            self::DEVICE_NAME_KEY,
            self::EXPIRES_AT_KEY,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Build the cache key for a pending opaque token.
     *
     * @param  string $token the opaque token
     * @return string the cache key
     */
    private static function pendingCacheKey(string $token): string
    {
        return self::PENDING_CACHE_PREFIX.$token;
    }

    /**
     * Build the cache key mapping a User id to their current opaque token.
     *
     * @param  int    $userId the User id
     * @return string the cache key
     */
    private static function pendingUserCacheKey(int $userId): string
    {
        return self::PENDING_USER_PREFIX.$userId;
    }
}
