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
 * IP-keyed rate limits. Resolution order is the explicit override, then the
 * per-environment default for a deployed environment, then nothing for
 * `local` / `testing` and for an environment that cannot be named.
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
     * Record the process environment so each test starts from a known state.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        putenv('APP_ENV=testing');
    }

    /**
     * Clear every variable the resolver reads so tests stay isolated.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        putenv('TRUSTED_PROXIES');
        putenv('TRUSTED_PROXIES_PRODUCTION');
        putenv('TRUSTED_PROXIES_STAGING');
        putenv('TRUSTED_PROXIES_LOCAL');
        putenv('TRUSTED_PROXIES_TESTING');
        putenv('APP_ENV=testing');

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
     * Keep an IPv6 CIDR intact, so a v6 proxy prefix is trusted like its IPv4
     * counterpart.
     */
    #[Test]
    public function it_keeps_an_ipv6_cidr(): void
    {
        putenv('TRUSTED_PROXIES=2400:cb00::/32, 2001:db8:1::/48');

        $this->assertSame(['2400:cb00::/32', '2001:db8:1::/48'], TrustedProxies::all());
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

    /**
     * Read the deployed default for the running environment when the override
     * is unset.
     */
    #[Test]
    public function it_reads_the_environment_default_when_the_override_is_unset(): void
    {
        // Arrange

        putenv('APP_ENV=production');
        putenv('TRUSTED_PROXIES');
        putenv('TRUSTED_PROXIES_PRODUCTION=192.0.2.0/24,2001:db8:1::/48');

        // Act + Assert

        $this->assertSame(['192.0.2.0/24', '2001:db8:1::/48'], TrustedProxies::all());
    }

    /**
     * Read the staging default only for the staging environment, so one
     * environment's proxy ranges are never trusted by another.
     */
    #[Test]
    public function it_reads_only_the_running_environments_default(): void
    {
        // Arrange

        putenv('APP_ENV=staging');
        putenv('TRUSTED_PROXIES');
        putenv('TRUSTED_PROXIES_PRODUCTION=192.0.2.0/24');
        putenv('TRUSTED_PROXIES_STAGING=198.51.100.0/24');

        // Act + Assert

        $this->assertSame(['198.51.100.0/24'], TrustedProxies::all());
    }

    /**
     * Let the override win over the environment default.
     */
    #[Test]
    public function it_prefers_the_override_over_the_environment_default(): void
    {
        // Arrange

        putenv('APP_ENV=production');
        putenv('TRUSTED_PROXIES=203.0.113.9');
        putenv('TRUSTED_PROXIES_PRODUCTION=192.0.2.0/24');

        // Act + Assert

        $this->assertSame(['203.0.113.9'], TrustedProxies::all());
    }

    /**
     * Trust nothing in `local` and `testing` even when a per-environment
     * variable is set: a developer machine is directly exposed.
     */
    #[Test]
    public function it_trusts_nothing_in_local_and_testing(): void
    {
        // Arrange

        putenv('TRUSTED_PROXIES');
        putenv('TRUSTED_PROXIES_TESTING=192.0.2.0/24');

        // Act + Assert

        $this->assertSame([], TrustedProxies::all());

        // Arrange

        putenv('APP_ENV=local');
        putenv('TRUSTED_PROXIES_LOCAL=192.0.2.0/24');

        // Act + Assert

        $this->assertSame([], TrustedProxies::all());

        putenv('TRUSTED_PROXIES_LOCAL');
    }

    /**
     * Trust nothing when the environment cannot be named, rather than guessing
     * a deployed default.
     */
    #[Test]
    public function it_trusts_nothing_when_the_environment_is_unset(): void
    {
        // Arrange

        putenv('APP_ENV');
        putenv('TRUSTED_PROXIES');
        putenv('TRUSTED_PROXIES_PRODUCTION=192.0.2.0/24');

        // Act + Assert

        $this->assertSame([], TrustedProxies::all());
    }

    /**
     * Drop a wildcard that appears in an environment default, not just in the
     * override.
     */
    #[Test]
    public function it_rejects_a_wildcard_in_the_environment_default(): void
    {
        // Arrange

        putenv('APP_ENV=production');
        putenv('TRUSTED_PROXIES');
        putenv('TRUSTED_PROXIES_PRODUCTION=192.0.2.0/24,*');

        // Act + Assert

        $this->assertSame(['192.0.2.0/24'], TrustedProxies::all());
    }
}
