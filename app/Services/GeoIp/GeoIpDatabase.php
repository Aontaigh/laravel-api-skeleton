<?php

declare(strict_types=1);

namespace App\Services\GeoIp;

use GeoIp2\Database\Reader;
use Throwable;

/**
 * Holds the opened GeoLite2 City Reader for the lifetime of the process.
 *
 * Safe as a singleton: the MMDB is an immutable read-only file and this class
 * stores no request, User, IP, or last-result fields. After `geoip:update`,
 * long-lived PHP-FPM workers keep serving the previously opened file until
 * they are recycled (or Sail is restarted) - acceptable for enrichment data,
 * and the reason `geoip:update` leaves no runtime output hint.
 */
final class GeoIpDatabase
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    private readonly ?Reader $reader;

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Open the configured MMDB once, or keep a null Reader when it is absent.
     */
    public function __construct()
    {
        $path = self::resolvedDatabasePath();

        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            $this->reader = null;

            return;
        }

        /*
         * Reader throws InvalidDatabaseException on a corrupt MMDB. PHPStan
         * cannot see that throw from the constructor, so this catch is
         * Throwable: fail-open rather than taking session registration down.
         */
        try {
            $this->reader = new Reader($path);
        } catch (Throwable) {
            $this->reader = null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve `geoip.database` against the project root.
     *
     * `.env` ships a relative `storage/geoip/...` path. Artisan's cwd is the
     * project root, so `is_file()` works in tinker. A long-running process
     * whose cwd is not the project root would silently miss the MMDB, so a
     * relative path is always anchored to `base_path()`.
     *
     * @return string the absolute database path, or empty when unset
     */
    public static function resolvedDatabasePath(): string
    {
        $path = config()->string('geoip.database');

        if ($path === '' || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * The opened MaxMind Reader, or null when the database is missing or unreadable.
     *
     * @return Reader|null the process-lifetime Reader
     */
    public function reader(): ?Reader
    {
        return $this->reader;
    }
}
