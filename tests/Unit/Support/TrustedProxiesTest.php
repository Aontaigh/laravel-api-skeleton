<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\TrustedProxies;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for the trusted-proxy resolution.
 *
 * The safe posture is trusting nothing: an empty or unset `TRUSTED_PROXIES`
 * yields an empty list, a wildcard is rejected, and a comma-separated list is
 * trimmed - a wildcard would let clients spoof `X-Forwarded-For` and bypass
 * IP-keyed rate limits.
 */
#[CoversClass(TrustedProxies::class)]
final class TrustedProxiesTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Setup / Teardown
    |--------------------------------------------------------------------------
    */

    /**
     * Clear the environment variable so tests stay isolated.
     */
    protected function tearDown(): void
    {
        putenv('TRUSTED_PROXIES');

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Trust nothing when the environment variable is unset.
     */
    #[Test]
    public function it_trusts_nothing_when_unset(): void
    {
        putenv('TRUSTED_PROXIES');

        $this->assertSame([], TrustedProxies::all());
    }

    /**
     * Trust nothing when the environment variable is blank.
     */
    #[Test]
    public function it_trusts_nothing_when_blank(): void
    {
        putenv('TRUSTED_PROXIES=');

        $this->assertSame([], TrustedProxies::all());
    }

    /**
     * Parse and trim a comma-separated proxy list.
     */
    #[Test]
    public function it_parses_a_comma_separated_list(): void
    {
        putenv('TRUSTED_PROXIES=10.0.0.5, 10.0.0.0/24 ,192.168.1.1');

        $this->assertSame(['10.0.0.5', '10.0.0.0/24', '192.168.1.1'], TrustedProxies::all());
    }

    /**
     * Reject a wildcard: trusting all proxies lets clients spoof their IP.
     */
    #[Test]
    public function it_rejects_a_wildcard(): void
    {
        putenv('TRUSTED_PROXIES=*');

        $this->assertSame([], TrustedProxies::all());
    }

    /**
     * Drop blank entries from a messy list instead of trusting empty strings.
     */
    #[Test]
    public function it_drops_blank_entries(): void
    {
        putenv('TRUSTED_PROXIES=10.0.0.5,, ,10.0.0.6');

        $this->assertSame(['10.0.0.5', '10.0.0.6'], TrustedProxies::all());
    }
}
