<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalises a caller address into the form used by address-derived rate-limit keys.
 *
 * A raw caller address is a poor bucket identity on IPv6. An ISP hands one
 * subscriber a `/64`, and the subscriber may use any of 2^64 addresses inside
 * it; hashing the raw address would mint a fresh rate-limit bucket with every
 * request and let one caller spend an allowance per address. An IPv6 address is
 * therefore collapsed to its `/64` network, the standard subscriber prefix
 * boundary ([RFC 4291 section 2.5.1](https://www.rfc-editor.org/rfc/rfc4291#section-2.5.1) -
 * an address splits into a 64-bit subnet prefix and a 64-bit interface
 * identifier). IPv4 addresses are returned unchanged: one address is already
 * one subscriber.
 *
 * Output is the canonical text form `inet_ntop()` produces, which matches
 * [RFC 5952](https://www.rfc-editor.org/rfc/rfc5952) - lowercase, zeroes
 * compressed - suffixed with the `/64` prefix length, so the same network always
 * yields the same key regardless of how the caller spelled it, and the key reads
 * as a range rather than a host
 * ([RFC 4632 section 3.1](https://www.rfc-editor.org/rfc/rfc4632#section-3.1)).
 */
final class IpAddress
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * Length of an IPv6 address in its packed binary form.
     */
    private const int IPV6_BYTES = 16;

    /**
     * Bytes of prefix kept by the `/64` mask; the remaining bytes are zeroed.
     */
    private const int NETWORK_PREFIX_BYTES = 8;

    /**
     * Binary prefix of an IPv4-mapped IPv6 address (`::ffff:0:0/96`).
     *
     * Standard: [RFC 4291 section 2.5.5.1](https://www.rfc-editor.org/rfc/rfc4291#section-2.5.5.1).
     */
    private const string IPV4_MAPPED_PREFIX = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff";

    /**
     * Byte offset of the embedded IPv4 address inside an IPv4-mapped IPv6 address.
     */
    private const int IPV4_MAPPED_OFFSET = 12;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Normalise an address for use in a rate-limit key.
     *
     * A value that is not an IP at all is returned verbatim rather than thrown
     * on: the limiter runs in middleware before validation, so a malformed
     * address must still produce a usable bucket instead of a server error.
     *
     * @param  string|null $ip the caller address, as `$request->ip()` reports it
     * @return string      the IPv4 address unchanged, the IPv6 `/64` network in CIDR form, or an empty string when unset
     */
    public static function normalise(?string $ip): string
    {
        if ($ip === null || $ip === '') {
            return '';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $ip;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return $ip;
        }

        $packed = inet_pton($ip);

        if ($packed === false) {
            return $ip;
        }

        /*
         * `::ffff:a.b.c.d` is an IPv4 caller wearing the IPv6 text form. Its
         * `/64` is the `::/64` prefix, which would drop every such caller into
         * one bucket, so unwrap the embedded IPv4 address first.
         */
        if (str_starts_with($packed, self::IPV4_MAPPED_PREFIX)) {
            $mapped = inet_ntop(substr($packed, self::IPV4_MAPPED_OFFSET));

            return $mapped === false ? $ip : $mapped;
        }

        $network = inet_ntop(
            substr($packed, 0, self::NETWORK_PREFIX_BYTES).str_repeat("\x00", self::IPV6_BYTES - self::NETWORK_PREFIX_BYTES),
        );

        return $network === false ? $ip : $network.'/64';
    }
}
