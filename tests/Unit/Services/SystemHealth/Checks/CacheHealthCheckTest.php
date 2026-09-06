<?php

declare(strict_types=1);

namespace Tests\Unit\Services\SystemHealth\Checks;

use App\DataTransferObjects\SystemHealth\SystemHealthCheckResult;
use App\Enums\SystemHealthStatus;
use App\Services\SystemHealth\Checks\CacheHealthCheck;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\UnitTestCase;

/**
 * Unit tests for the CacheHealthCheck probe.
 */
#[CoversClass(CacheHealthCheck::class)]
#[CoversClass(SystemHealthCheckResult::class)]
final class CacheHealthCheckTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Report Up when the put/get round trip returns the written value.
     */
    #[Test]
    public function it_reports_up_when_the_round_trip_succeeds(): void
    {
        // Arrange

        Cache::shouldReceive('put')->once()->andReturn(true);
        Cache::shouldReceive('get')->once()->andReturn(true);
        Cache::shouldReceive('forget')->once()->andReturn(true);

        $check = new CacheHealthCheck;

        // Act

        $result = $check->check();

        // Assert

        $this->assertSame(SystemHealthStatus::Up, $result->status);
        $this->assertNull($result->message);
    }

    /**
     * Report Down when the store answers with a value other than what was written.
     */
    #[Test]
    public function it_reports_down_when_the_round_trip_returns_an_unexpected_value(): void
    {
        // Arrange

        Cache::shouldReceive('put')->once()->andReturn(true);
        Cache::shouldReceive('get')->once()->andReturn(false);
        Cache::shouldReceive('forget')->once()->andReturn(true);

        $check = new CacheHealthCheck;

        // Act

        $result = $check->check();

        // Assert

        $this->assertSame(SystemHealthStatus::Down, $result->status);
        $this->assertSame('Cache Round Trip Returned an Unexpected Value', $result->message);
    }

    /**
     * Report Down (never throw) when the cache store itself fails.
     */
    #[Test]
    public function it_reports_down_when_the_cache_store_throws(): void
    {
        // Arrange

        Cache::shouldReceive('put')->once()->andThrow(new RuntimeException('Redis went away'));

        $check = new CacheHealthCheck;

        // Act

        $result = $check->check();

        // Assert

        $this->assertSame(SystemHealthStatus::Down, $result->status);
        $this->assertSame('Redis went away', $result->message);
    }

    /**
     * Bound a pathological upstream error message before it can be persisted.
     */
    #[Test]
    public function it_bounds_a_failing_probe_s_message(): void
    {
        // Arrange

        Cache::shouldReceive('put')->once()->andThrow(new RuntimeException(str_repeat('x', 500)));

        $check = new CacheHealthCheck;

        // Act

        $result = $check->check();

        // Assert

        $this->assertSame(255, mb_strlen((string) $result->message));
    }

    /**
     * Identify the component slug persisted to `system_health_checks`.
     */
    #[Test]
    public function it_reports_the_cache_component_slug(): void
    {
        // Arrange

        $check = new CacheHealthCheck;

        // Act

        $component = $check->component();

        // Assert

        $this->assertSame('cache', $component);
    }
}
