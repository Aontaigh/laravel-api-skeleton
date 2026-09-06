<?php

declare(strict_types=1);

namespace Tests\Unit\DataTransferObjects\GeoIp;

use App\DataTransferObjects\GeoIp\GeoIpLocation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for the GeoIpLocation value object.
 */
#[CoversClass(GeoIpLocation::class)]
final class GeoIpLocationTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Carry the city and country passed at construction.
     */
    #[Test]
    public function it_carries_the_city_and_country(): void
    {
        // Arrange

        // Act

        $location = new GeoIpLocation(city: 'Mountain View', country: 'US');

        // Assert

        $this->assertSame('Mountain View', $location->city);
        $this->assertSame('US', $location->country);
    }

    /**
     * Allow either field to be null so partial lookups still persist.
     */
    #[Test]
    public function it_allows_null_fields(): void
    {
        // Arrange

        // Act

        $location = new GeoIpLocation(city: null, country: null);

        // Assert

        $this->assertNull($location->city);
        $this->assertNull($location->country);
    }
}
