<?php

declare(strict_types=1);

namespace Tests\Unit\Services\GeoIp;

use App\Services\GeoIp\GeoIpDatabase;
use App\Services\GeoIp\MaxMindGeoIpLocator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsGeoLiteCityDatabase;
use Tests\UnitTestCase;

/**
 * Unit tests for MaxMindGeoIpLocator fail-open behaviour.
 */
#[CoversClass(MaxMindGeoIpLocator::class)]
#[CoversClass(GeoIpDatabase::class)]
final class MaxMindGeoIpLocatorTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use BuildsGeoLiteCityDatabase;

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /*
     * Fail-Open Tests
     * ---------------
     */

    /**
     * Return null when the GeoLite2 database file is absent.
     */
    #[Test]
    public function it_returns_null_when_the_database_file_is_missing(): void
    {
        // Arrange

        config(['geoip.database' => storage_path('geoip/does-not-exist.mmdb')]);

        $locator = new MaxMindGeoIpLocator(new GeoIpDatabase);

        // Act

        $location = $locator->locate('8.8.8.8');

        // Assert

        $this->assertNull($location);
    }

    /*
     * Range Skip Tests
     * ----------------
     */

    /**
     * Return null for a missing or empty IP.
     */
    #[Test]
    public function it_returns_null_for_a_blank_ip(): void
    {
        // Arrange

        $locator = new MaxMindGeoIpLocator(new GeoIpDatabase);

        // Act

        $location = $locator->locate('');

        // Assert

        $this->assertNull($location);
    }

    /**
     * Skip private and reserved IPs without opening a Reader lookup.
     *
     * PHPUnit runs as `testing`, not `local`, so the production skip still applies.
     */
    #[Test]
    #[DataProvider('privateIpProvider')]
    public function it_returns_null_for_private_or_reserved_ips(string $ipAddress): void
    {
        // Arrange

        $path = $this->writeGeoLiteCityDatabase();

        config(['geoip.database' => $path]);

        $locator = new MaxMindGeoIpLocator(new GeoIpDatabase);

        // Act

        $location = $locator->locate($ipAddress);

        // Assert

        $this->assertNull($location);

        unlink($path);
    }

    /*
     * Public Lookups Tests
     * --------------------
     */

    /**
     * Resolve city and country for a public IP present in the database.
     */
    #[Test]
    public function it_resolves_city_and_country_for_a_public_ip(): void
    {
        // Arrange

        $path = $this->writeGeoLiteCityDatabase();

        config(['geoip.database' => $path]);

        $locator = new MaxMindGeoIpLocator(new GeoIpDatabase);

        // Act

        $location = $locator->locate('8.8.8.8');

        // Assert

        $this->assertNotNull($location);
        $this->assertSame('Mountain View', $location->city);
        $this->assertSame('US', $location->country);

        unlink($path);
    }

    /*
     * Local Fallback Tests
     * --------------------
     */

    /**
     * Fail open to null for a public IP the database cannot map.
     */
    #[Test]
    public function it_fails_open_for_an_unmapped_public_ip(): void
    {
        // Arrange

        $path = $this->writeGeoLiteCityDatabase();

        config(['geoip.database' => $path]);

        $locator = new MaxMindGeoIpLocator(new GeoIpDatabase);

        // Act

        $location = $locator->locate('203.0.113.1');

        // Assert

        $this->assertNull($location);

        unlink($path);
    }

    /**
     * Look up `geoip.local_fallback_ip` in `local` when the request IP is private.
     *
     * Sail logins use 172.x / 127.0.0.1, which GeoLite2 cannot map, so the
     * configured public fallback stands in for the real caller.
     */
    #[Test]
    public function it_looks_up_the_local_fallback_ip_for_private_addresses_in_local(): void
    {
        // Arrange

        $path = $this->writeGeoLiteCityDatabase();

        $this->app['env'] = 'local';
        config([
            'geoip.database' => $path,
            'geoip.local_fallback_ip' => '8.8.8.8',
        ]);

        $locator = new MaxMindGeoIpLocator(new GeoIpDatabase);

        // Act

        $location = $locator->locate('172.19.0.1');

        // Assert

        $this->assertNotNull($location);
        $this->assertSame('Mountain View', $location->city);
        $this->assertSame('US', $location->country);

        unlink($path);
    }

    /**
     * Keep skipping private IPs when `local` has no usable fallback.
     */
    #[Test]
    public function it_fails_open_in_local_when_the_fallback_is_not_configured(): void
    {
        // Arrange

        $this->app['env'] = 'local';
        config([
            'geoip.database' => storage_path('geoip/does-not-exist.mmdb'),
            'geoip.local_fallback_ip' => '',
        ]);

        $locator = new MaxMindGeoIpLocator(new GeoIpDatabase);

        // Act

        $location = $locator->locate('127.0.0.1');

        // Assert

        $this->assertNull($location);
    }

    /**
     * Ignore a non-public `local` fallback IP and skip the lookup entirely.
     */
    #[Test]
    public function it_ignores_a_private_local_fallback_ip(): void
    {
        // Arrange

        $path = $this->writeGeoLiteCityDatabase();

        $this->app['env'] = 'local';
        config([
            'geoip.database' => $path,
            'geoip.local_fallback_ip' => '192.168.0.1',
        ]);

        $locator = new MaxMindGeoIpLocator(new GeoIpDatabase);

        // Act

        $location = $locator->locate('172.19.0.1');

        // Assert

        $this->assertNull($location);

        unlink($path);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Private and reserved addresses that must never be looked up.
     *
     * @return array<string, array{0: string}> case name mapped to [ipAddress]
     */
    public static function privateIpProvider(): array
    {
        return [
            'loopback ipv4' => ['127.0.0.1'],
            'rfc1918 ten' => ['10.0.0.1'],
            'rfc1918 one nine two' => ['192.168.1.1'],
            'link local' => ['169.254.1.1'],
            'loopback ipv6' => ['::1'],
        ];
    }
}
