<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Contracts\Webhooks\WebhookDnsResolver;

/**
 * Map-backed DNS fake for webhook URL screening tests.
 */
final class FakeWebhookDnsResolver implements WebhookDnsResolver
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new fake resolver.
     *
     * @param array<string, list<string>> $hosts hostnames mapped to fake addresses
     */
    public function __construct(
        private readonly array $hosts,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve a hostname from the fake map.
     *
     * @param  string       $host the hostname to resolve
     * @return list<string> the mapped addresses, empty when unknown
     */
    public function resolve(string $host): array
    {
        return $this->hosts[$host] ?? [];
    }
}
