<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Contracts\Webhooks\WebhookDnsResolver;

/**
 * Production DNS resolver for webhook SSRF screening.
 */
final class SystemWebhookDnsResolver implements WebhookDnsResolver
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
    public function resolve(string $host): array
    {
        $resolved = gethostbynamel($host);

        $addresses = $resolved === false ? [] : $resolved;

        $aaaaRecords = dns_get_record($host, DNS_AAAA);

        if (is_array($aaaaRecords)) {
            foreach ($aaaaRecords as $record) {
                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($addresses));
    }
}
