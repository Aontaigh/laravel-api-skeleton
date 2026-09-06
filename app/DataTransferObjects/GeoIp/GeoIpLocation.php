<?php

declare(strict_types=1);

namespace App\DataTransferObjects\GeoIp;

/**
 * City and ISO country resolved from an IP address.
 */
final readonly class GeoIpLocation
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new GeoIpLocation value object.
     *
     * @param string|null $city    the city name, when MaxMind has one
     * @param string|null $country the ISO 3166-1 alpha-2 country code
     */
    public function __construct(
        public ?string $city,
        public ?string $country,
    ) {}
}
