<?php

declare(strict_types=1);

namespace App\Contracts\Webhooks;

/**
 * Resolves a hostname to its IP addresses for webhook SSRF screening.
 *
 * Behind the interface so tests never perform real DNS: bind a fake mapping
 * instead. See `App\Services\Webhooks\SystemWebhookDnsResolver`.
 */
interface WebhookDnsResolver
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve a hostname to its IPv4/IPv6 addresses.
     *
     * @param  string       $host the hostname to resolve
     * @return list<string> the resolved IP addresses, empty when unresolvable
     */
    public function resolve(string $host): array;
}
