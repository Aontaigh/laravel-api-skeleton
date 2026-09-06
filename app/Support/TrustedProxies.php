<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resolves the reverse-proxy trust list for the running environment.
 *
 * Behind a load balancer every request arrives from the proxy address: without
 * an explicit trust list, IP-keyed rate limits collapse into one shared bucket
 * and audit rows record only the proxy. The list is empty by default (trust
 * nothing - the safe posture for direct exposure) and is set per environment
 * via a comma-separated `TRUSTED_PROXIES` variable. The value is never `*`:
 * a wildcard would let clients spoof `X-Forwarded-For` and bypass IP limits.
 *
 * Resolved with `getenv()`, not `config()`: `bootstrap/app.php` evaluates the
 * trust list while the application is being configured, before the container
 * has a `config` binding, so the config helper is not available here.
 */
final class TrustedProxies
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the trusted-proxy list from the environment.
     *
     * @return list<string> the trusted proxy IPs / CIDRs (empty when unset)
     */
    public static function all(): array
    {
        $override = getenv('TRUSTED_PROXIES');

        if (! is_string($override) || trim($override) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $override)),
            static fn (string $proxy): bool => $proxy !== '' && $proxy !== '*',
        ));
    }
}
