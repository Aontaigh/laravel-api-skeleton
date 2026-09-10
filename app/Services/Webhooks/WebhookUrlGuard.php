<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Contracts\Webhooks\WebhookDnsResolver;
use Illuminate\Support\Facades\App;

/**
 * Screens webhook target URLs against server-side request forgery.
 *
 * Creation and update only: an attacker who can point an endpoint at
 * `http://169.254.169.254/` turns every domain event into a cloud-metadata
 * probe from inside the network. Delivery-time re-resolution would still race
 * a DNS rebinding swap (check time vs send time), so this is documented as
 * the practical starter posture, not a TOCTOU-proof guarantee - same trade-off
 * as most managed webhook providers document.
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
     * every resolved address must be public: private, reserved, and loopback
     * ranges are refused.
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
     * Whether the IP address is public (not private, reserved, or loopback).
     *
     * @param  string $ip the IP address to screen
     * @return bool   true when the address is public
     */
    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
