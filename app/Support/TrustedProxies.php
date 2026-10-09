<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resolves the reverse-proxy trust list for the running environment.
 *
 * Behind a load balancer every request arrives from the proxy address: without
 * an explicit trust list, IP-keyed rate limits collapse into one shared bucket
 * and audit rows record only the proxy. A deployed task that omits the list is
 * therefore not merely less precise - one caller can spend everyone's sign-in
 * allowance - so each deployed environment carries a default the operator fills.
 *
 * Resolution order:
 *
 * 1. `TRUSTED_PROXIES` when set (non-blank) - the explicit override, wins
 *    everywhere.
 * 2. Empty for `local`, for `testing`, and for any environment that cannot be
 *    named - trust nothing is the safe posture for direct exposure.
 * 3. `TRUSTED_PROXIES_{ENVIRONMENT}` otherwise - the deployed default, e.g.
 *    `TRUSTED_PROXIES_PRODUCTION` / `TRUSTED_PROXIES_STAGING`. Fill it with the
 *    load balancer's subnets plus the CDN's published ranges, IPv6 included:
 *    Cloudflare, for example, publishes `2400:cb00::/32` alongside its IPv4
 *    ranges (https://www.cloudflare.com/ips/). Laravel resolves the caller
 *    through the same Symfony CIDR matcher for both families, so an IPv6 entry
 *    is trusted exactly like its IPv4 counterpart.
 *
 * The resolved list is never `*`: a wildcard would let clients spoof
 * `X-Forwarded-For` and bypass IP limits, so it is dropped wherever it appears.
 *
 * Read from the process environment with `getenv()`, not `config()`:
 * `bootstrap/app.php` resolves this list while the application is being
 * configured, before the container has a `config` binding or has loaded `.env`.
 * A deployment must therefore export the variables into the process (a
 * container `environment:` block, a systemd `Environment=`, a shell export);
 * values that exist only in `.env` are not visible at this point.
 */
final class TrustedProxies
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * Environment variable holding the explicit override list.
     */
    private const string OVERRIDE_VARIABLE = 'TRUSTED_PROXIES';

    /**
     * Prefix of the per-environment default variable, e.g. `TRUSTED_PROXIES_PRODUCTION`.
     */
    private const string DEFAULT_PREFIX = 'TRUSTED_PROXIES_';

    /**
     * Environments that are directly exposed and must trust nothing by default.
     */
    private const array UNTRUSTED_ENVIRONMENTS = ['local', 'testing'];

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the trusted-proxy list for the current process.
     *
     * @return list<string> the trusted proxy IPs / CIDRs (empty when nothing is configured)
     */
    public static function all(): array
    {
        $raw = self::value(self::OVERRIDE_VARIABLE) ?? self::environmentDefault();

        return self::parse($raw ?? '');
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Read the deployed default for the running environment.
     *
     * @return string|null the configured list, or null when the environment trusts nothing
     */
    private static function environmentDefault(): ?string
    {
        $environment = self::value('APP_ENV');

        if ($environment === null || in_array(strtolower($environment), self::UNTRUSTED_ENVIRONMENTS, true)) {
            return null;
        }

        $suffix = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '_', $environment));

        return self::value(self::DEFAULT_PREFIX.$suffix);
    }

    /**
     * Read a non-blank process environment variable.
     *
     * A blank value reads as unset: setting `TRUSTED_PROXIES=` means "not
     * configured", so it falls through to the environment default rather than
     * silently disabling the override's neighbours.
     *
     * @param  string      $variable the variable name
     * @return string|null the trimmed value, or null when unset or blank
     */
    private static function value(string $variable): ?string
    {
        $value = getenv($variable);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * Split, trim, and filter a comma-separated list.
     *
     * @param  string       $raw the raw value
     * @return list<string> the entries, with blanks and wildcards removed
     */
    private static function parse(string $raw): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            static fn (string $proxy): bool => $proxy !== '' && $proxy !== '*',
        ));
    }
}
