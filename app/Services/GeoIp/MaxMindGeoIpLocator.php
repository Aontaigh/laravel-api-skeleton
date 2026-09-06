<?php

declare(strict_types=1);

namespace App\Services\GeoIp;

use App\Contracts\GeoIp\GeoIpLocator;
use App\DataTransferObjects\GeoIp\GeoIpLocation;
use Throwable;

/**
 * Resolves city and country from a local GeoLite2-City MMDB.
 *
 * Bound per resolution (not a singleton) so no lookup state can survive
 * across requests. The opened Reader lives on {@see GeoIpDatabase}.
 */
final class MaxMindGeoIpLocator implements GeoIpLocator
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** Cap attacker-influenced city names before persist. */
    private const int MAX_CITY_LENGTH = 255;

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new MaxMindGeoIpLocator.
     *
     * @param GeoIpDatabase $database the process-lifetime opened Reader holder
     */
    public function __construct(
        private readonly GeoIpDatabase $database,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve city and country for the given IP, or null when lookup fails open.
     *
     * Missing files, unmapped addresses, and any throw fail open to null.
     * Private and reserved ranges are skipped outside `local` (they are never
     * in GeoLite2). In `local`, Sail/loopback IPs are looked up via
     * `geoip.local_fallback_ip` so Active Sessions still show a city.
     *
     * @param  string|null        $ipAddress the client IP captured at the call site
     * @return GeoIpLocation|null the location, or null when lookup is skipped or fails
     */
    public function locate(?string $ipAddress): ?GeoIpLocation
    {
        if ($ipAddress === null || $ipAddress === '') {
            return null;
        }

        $lookupIp = $this->lookupIp($ipAddress);

        if ($lookupIp === null) {
            return null;
        }

        $reader = $this->database->reader();

        if ($reader === null) {
            return null;
        }

        /*
         * `city()` throws AddressNotFoundException for unmapped IPs. PHPStan
         * cannot see that throw, so the catch is Throwable: fail-open.
         */
        try {
            $record = $reader->city($lookupIp);
        } catch (Throwable) {
            return null;
        }

        $city = $record->city->name;
        $country = $record->country->isoCode;

        $normalisedCity = is_string($city) && $city !== ''
            ? mb_substr($city, 0, self::MAX_CITY_LENGTH)
            : null;
        $normalisedCountry = is_string($country) && $country !== ''
            ? strtoupper(mb_substr($country, 0, 2))
            : null;

        if ($normalisedCity === null && $normalisedCountry === null) {
            return null;
        }

        return new GeoIpLocation(
            city: $normalisedCity,
            country: $normalisedCountry,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Choose the IP to query in GeoLite2.
     *
     * Private ranges are skipped in `staging` and `production`. `local` keeps
     * looking up: Sail's 172.x / 127.0.0.1 cannot hit MaxMind, so a configured
     * public fallback is used instead of returning null without a Reader call.
     *
     * @param  string      $ipAddress the client IP captured at the call site
     * @return string|null the address to pass to Reader::city(), or null to skip
     */
    private function lookupIp(string $ipAddress): ?string
    {
        if ($this->isPublicIp($ipAddress)) {
            return $ipAddress;
        }

        if (! app()->environment('local')) {
            return null;
        }

        $fallbackIp = config()->string('geoip.local_fallback_ip');

        if ($fallbackIp !== '' && $this->isPublicIp($fallbackIp)) {
            return $fallbackIp;
        }

        return $ipAddress;
    }

    /**
     * Whether the value is a public unicast IP MaxMind can usefully look up.
     *
     * @param  string $ipAddress the candidate IP
     * @return bool   true when the address is public IPv4 or IPv6
     */
    private function isPublicIp(string $ipAddress): bool
    {
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        return filter_var($ipAddress, FILTER_VALIDATE_IP, $flags) !== false;
    }
}
