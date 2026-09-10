<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Contracts\Webhooks\WebhookDnsResolver;

/**
 * Swaps the webhook DNS resolver for a deterministic fake.
 *
 * Production resolution hits real DNS, so endpoint SSRF tests bind a
 * map-backed fake instead: known hosts resolve to the mapped addresses and
 * everything else resolves to nothing (unresolvable hosts are rejected by
 * the guard, same as production).
 *
 * @mixin \Tests\TestCase
 */
trait FakesWebhookDns
{
    /*
    |--------------------------------------------------------------------------
    | Protected
    |--------------------------------------------------------------------------
    */

    /**
     * Bind a map-backed DNS fake for webhook URL screening.
     *
     * @param  array<string, list<string>> $hosts hostnames mapped to their fake addresses
     * @return void
     */
    protected function fakeWebhookDns(array $hosts = []): void
    {
        $resolver = new FakeWebhookDnsResolver($hosts);

        $this->app->bind(WebhookDnsResolver::class, fn (): WebhookDnsResolver => $resolver);
    }
}
