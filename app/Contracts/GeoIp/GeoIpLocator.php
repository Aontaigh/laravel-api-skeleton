<?php

declare(strict_types=1);

namespace App\Contracts\GeoIp;

use App\DataTransferObjects\GeoIp\GeoIpLocation;

/**
 * Looks up city and country for an IP address.
 *
 * Fail-open: missing database, private/reserved IPs, and lookup errors return
 * null so session registration and audit recording never depend on MaxMind
 * availability.
 */
interface GeoIpLocator
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve city and country for the given IP, or null when unknown.
     *
     * @param  string|null        $ipAddress the client IP captured at the call site
     * @return GeoIpLocation|null the location, or null when lookup is skipped or fails
     */
    public function locate(?string $ipAddress): ?GeoIpLocation;
}
