<?php

declare(strict_types=1);

namespace Tests\Unit\Services\GeoIp;

use App\Services\GeoIp\GeoIpDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsGeoLiteCityDatabase;
use Tests\UnitTestCase;

/**
 * Unit tests for GeoIpDatabase path resolution and fail-open opening.
 */
#[CoversClass(GeoIpDatabase::class)]
final class GeoIpDatabaseTest extends UnitTestCase
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

    /**
     * Prefix a relative `geoip.database` value with the project root.
     */
    #[Test]
    public function it_resolves_a_relative_configured_path_from_the_project_root(): void
    {
        // Arrange

        config(['geoip.database' => 'storage/geoip/GeoLite2-City.mmdb']);

        // Act

        $resolved = GeoIpDatabase::resolvedDatabasePath();

        // Assert

        $this->assertSame(
            base_path('storage/geoip/GeoLite2-City.mmdb'),
            $resolved,
        );
    }

    /**
     * Leave an already-absolute `geoip.database` value unchanged.
     */
    #[Test]
    public function it_leaves_an_absolute_configured_path_unchanged(): void
    {
        // Arrange

        $path = storage_path('geoip/GeoLite2-City.mmdb');

        config(['geoip.database' => $path]);

        // Act

        $resolved = GeoIpDatabase::resolvedDatabasePath();

        // Assert

        $this->assertSame($path, $resolved);
    }

    /**
     * Keep a null Reader when the configured file is absent.
     */
    #[Test]
    public function it_holds_a_null_reader_when_the_database_file_is_missing(): void
    {
        // Arrange

        config(['geoip.database' => storage_path('geoip/does-not-exist.mmdb')]);

        // Act

        $database = new GeoIpDatabase;

        // Assert

        $this->assertNull($database->reader());
    }

    /**
     * Open the Reader when the configured file is a valid MMDB.
     */
    #[Test]
    public function it_opens_the_reader_when_the_database_is_valid(): void
    {
        // Arrange

        $path = $this->writeGeoLiteCityDatabase();

        config(['geoip.database' => $path]);

        // Act

        $database = new GeoIpDatabase;

        // Assert

        $this->assertNotNull($database->reader());

        unlink($path);
    }

    /**
     * Keep a null Reader when the configured file is not a valid MMDB.
     */
    #[Test]
    public function it_holds_a_null_reader_when_the_database_is_corrupt(): void
    {
        // Arrange

        $path = sys_get_temp_dir().'/GeoLite2-City-corrupt-'.bin2hex(random_bytes(4)).'.mmdb';
        file_put_contents($path, 'not-a-real-mmdb');

        config(['geoip.database' => $path]);

        // Act

        $database = new GeoIpDatabase;

        // Assert

        $this->assertNull($database->reader());

        unlink($path);
    }
}
