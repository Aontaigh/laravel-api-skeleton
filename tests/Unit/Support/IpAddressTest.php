<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\IpAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for the address normalisation used by rate-limit keys.
 *
 * IPv4 is returned unchanged; IPv6 is collapsed to its `/64` network
 * ([RFC 4291 section 2.5.1](https://www.rfc-editor.org/rfc/rfc4291#section-2.5.1)),
 * in the canonical [RFC 5952](https://www.rfc-editor.org/rfc/rfc5952) text form.
 * The documentation prefix `2001:db8::/32`
 * ([RFC 3849](https://www.rfc-editor.org/rfc/rfc3849)) is used throughout so no
 * test depends on a routable address.
 */
#[CoversClass(IpAddress::class)]
final class IpAddressTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return an IPv4 address unchanged: one address is already one subscriber.
     */
    #[Test]
    public function it_returns_an_ipv4_address_unchanged(): void
    {
        // Act + Assert

        $this->assertSame('203.0.113.7', IpAddress::normalise('203.0.113.7'));
        $this->assertSame('8.8.8.8', IpAddress::normalise('8.8.8.8'));
    }

    /**
     * Collapse an IPv6 address to its `/64` network.
     */
    #[Test]
    public function it_collapses_an_ipv6_address_to_its_network(): void
    {
        // Act + Assert

        $this->assertSame('2001:db8::/64', IpAddress::normalise('2001:db8::1'));
        $this->assertSame('2001:db8::/64', IpAddress::normalise('2001:db8::dead:beef'));
    }

    /**
     * Keep two hosts in one `/64` together so a caller cannot mint a fresh
     * bucket per address.
     */
    #[Test]
    public function it_shares_one_key_inside_a_slash_64(): void
    {
        // Act + Assert

        $this->assertSame(
            IpAddress::normalise('2001:db8::1'),
            IpAddress::normalise('2001:db8::ffff:ffff:ffff'),
        );
    }

    /**
     * Split on the `/64` boundary: a host in the next subnet gets its own key,
     * because that boundary is where the interface identifier begins.
     */
    #[Test]
    public function it_separates_hosts_across_the_slash_64_boundary(): void
    {
        // Act + Assert

        $this->assertSame('2001:db8:0:1::/64', IpAddress::normalise('2001:db8:0:1::1'));
        $this->assertNotSame(
            IpAddress::normalise('2001:db8::1'),
            IpAddress::normalise('2001:db8:0:1::1'),
        );
    }

    /**
     * Canonicalise the text form so mixed-case and long-form spellings of one
     * network yield the same key (RFC 5952).
     */
    #[Test]
    public function it_canonicalises_the_ipv6_text_form(): void
    {
        // Act + Assert

        $this->assertSame('2001:db8::/64', IpAddress::normalise('2001:DB8::1'));
        $this->assertSame('2001:db8::/64', IpAddress::normalise('2001:0db8:0000:0000:0000:0000:0000:0001'));
    }

    /**
     * Unwrap an IPv4-mapped IPv6 address to its embedded IPv4 form, so every
     * mapped caller does not share the `::/64` bucket (RFC 4291 section 2.5.5.1).
     */
    #[Test]
    public function it_unwraps_an_ipv4_mapped_address(): void
    {
        // Act + Assert

        $this->assertSame('203.0.113.7', IpAddress::normalise('::ffff:203.0.113.7'));
    }

    /**
     * Return an empty string for an unset address so a limiter can still build
     * a key when the request carries none.
     */
    #[Test]
    public function it_returns_an_empty_string_when_unset(): void
    {
        // Act + Assert

        $this->assertSame('', IpAddress::normalise(null));
        $this->assertSame('', IpAddress::normalise(''));
    }

    /**
     * Return a non-address value verbatim rather than throwing: the limiter
     * runs in middleware before validation.
     */
    #[Test]
    public function it_returns_a_non_address_verbatim(): void
    {
        // Act + Assert

        $this->assertSame('not-an-ip', IpAddress::normalise('not-an-ip'));
        $this->assertSame('1.2.3.4.5', IpAddress::normalise('1.2.3.4.5'));
    }
}
