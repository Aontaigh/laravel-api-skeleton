<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Contracts\Webhooks\WebhookDnsResolver;
use Illuminate\Support\Facades\App;

/**
 * Screens webhook target URLs against server-side request forgery.
 *
 * Runs at creation, at update, and again immediately before each delivery: an
 * attacker who can point an endpoint at `http://169.254.169.254/` turns every
 * domain event into a cloud-metadata probe from inside the network, and a
 * hostname that resolved public at registration can resolve private later.
 * The delivery-time re-screen narrows that DNS-rebinding window but cannot
 * close it: the HTTP client resolves the host once more when it connects, so
 * this is the practical starter posture, not a TOCTOU-proof guarantee - same
 * trade-off as most managed webhook providers document.
 */
final class WebhookUrlGuard
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new WebhookUrlGuard.
     *
     * @param WebhookDnsResolver $dns resolves hostnames for range screening
     */
    public function __construct(
        private readonly WebhookDnsResolver $dns,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the target URL is safe to register as a webhook endpoint.
     *
     * Requires an `http(s)` URL with a host and no embedded credentials.
     * Plain `http` is accepted in `local` and `testing` only, so the suite
     * and docker-compose receivers work without TLS. Literal-IP hosts and
     * every resolved address must be public: private, loopback, carrier-grade
     * NAT, and 6to4 ranges are refused.
     *
     * @param  string $url the candidate target URL
     * @return bool   true when the URL may be registered
     */
    public function allows(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';

        if ($host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if ($scheme === 'https') {
            return $this->hostIsPublic($host);
        }

        if ($scheme === 'http' && App::environment(['local', 'testing'])) {
            return $this->hostIsPublic($host);
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the host resolves only to public IP addresses.
     *
     * @param  string $host the URL host or literal IP
     * @return bool   true when every address is public
     */
    private function hostIsPublic(string $host): bool
    {
        $host = $this->stripIpv6Brackets($host);

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->isPublicIp($host);
        }

        $addresses = $this->dns->resolve($host);

        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicIp($address)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Strip the brackets PHP's `parse_url` retains around IPv6 literals.
     *
     * @param  string $host the URL host or literal IP
     * @return string the host without surrounding brackets
     */
    private function stripIpv6Brackets(string $host): string
    {
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            return substr($host, 1, -1);
        }

        return $host;
    }

    /**
     * Whether the IP address is public (not private, reserved, or loopback).
     *
     * `FILTER_FLAG_NO_PRIV_RANGE` and `FILTER_FLAG_NO_RES_RANGE` do not cover
     * carrier-grade NAT (100.64.0.0/10) or 6to4 addresses (2002::/16), so both
     * are screened explicitly: a 6to4 address is public only when its embedded
     * IPv4 address is public.
     *
     * @param  string $ip the IP address to screen
     * @return bool   true when the address is public
     */
    private function isPublicIp(string $ip): bool
    {
        $ip = $this->stripIpv6Brackets($ip);

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return ! $this->isCgnatIpv4($ip)
                && filter_var(
                    $ip,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
                ) !== false;
        }

        if (str_starts_with(strtolower($ip), '2002:')) {
            return $this->isPublicSixToFour($ip);
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /**
     * Whether the IPv4 address falls in the carrier-grade NAT shared range.
     *
     * @param  string $ip the IPv4 address to screen
     * @return bool   true when the address is in 100.64.0.0/10
     */
    private function isCgnatIpv4(string $ip): bool
    {
        $long = ip2long($ip);
        $start = ip2long('100.64.0.0');
        $end = ip2long('100.127.255.255');

        if ($long === false || $start === false || $end === false) {
            return false;
        }

        return $long >= $start && $long <= $end;
    }

    /**
     * Whether the 6to4 address embeds a public IPv4 address.
     *
     * @param  string $ip the 6to4 address to screen
     * @return bool   true when the embedded IPv4 address is public
     */
    private function isPublicSixToFour(string $ip): bool
    {
        $packed = inet_pton($ip);

        if ($packed === false || strlen($packed) !== 16) {
            return false;
        }

        $embedded = inet_ntop(substr($packed, 2, 4));

        if ($embedded === false) {
            return false;
        }

        return $this->isPublicIp($embedded);
    }
}
