<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ApiDateTime;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for API Datetime serialisation.
 */
#[CoversClass(ApiDateTime::class)]
final class ApiDateTimeTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Serialise datetimes to ISO-8601 UTC without mutating the source.
     */
    #[Test]
    public function it_serialises_datetimes_to_iso8601_utc_without_mutating_the_source(): void
    {
        // Arrange

        $value = CarbonImmutable::parse('2026-08-05 15:30:00', 'Europe/Dublin');

        // Act

        $serialised = ApiDateTime::serialize($value);

        // Assert

        $this->assertSame('2026-08-05T14:30:00+00:00', $serialised);
        $this->assertSame('Europe/Dublin', $value->timezone->getName());
    }

    /**
     * Return null for a null datetime.
     */
    #[Test]
    public function it_returns_null_for_a_null_datetime(): void
    {
        // Act

        $serialised = ApiDateTime::serialize(null);

        // Assert

        $this->assertNull($serialised);
    }
}
