<?php

declare(strict_types=1);

namespace Tests\Unit\Services\SystemHealth\Checks;

use App\DataTransferObjects\SystemHealth\SystemHealthCheckResult;
use App\Enums\SystemHealthStatus;
use App\Services\SystemHealth\Checks\DatabaseHealthCheck;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\UnitTestCase;

/**
 * Unit tests for the DatabaseHealthCheck probe.
 */
#[CoversClass(DatabaseHealthCheck::class)]
#[CoversClass(SystemHealthCheckResult::class)]
final class DatabaseHealthCheckTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Report Up when `select 1` succeeds quickly.
     */
    #[Test]
    public function it_reports_up_when_the_probe_query_succeeds(): void
    {
        // Arrange

        DB::shouldReceive('select')->once()->with('select 1')->andReturn(null);

        $check = new DatabaseHealthCheck;

        // Act

        $result = $check->check();

        // Assert

        $this->assertSame(SystemHealthStatus::Up, $result->status);
        $this->assertIsInt($result->responseTimeMs);
        $this->assertGreaterThanOrEqual(0, $result->responseTimeMs);
        $this->assertNull($result->message);
    }

    /**
     * Report Down (never throw) when the probe query fails.
     */
    #[Test]
    public function it_reports_down_when_the_probe_query_fails(): void
    {
        // Arrange

        DB::shouldReceive('select')->once()->with('select 1')->andThrow(new RuntimeException('Connection refused'));

        $check = new DatabaseHealthCheck;

        // Act

        $result = $check->check();

        // Assert

        $this->assertSame(SystemHealthStatus::Down, $result->status);
        $this->assertSame('Connection refused', $result->message);
    }

    /**
     * Bound a pathological upstream error message before it can be persisted.
     */
    #[Test]
    public function it_bounds_a_failing_probe_s_message(): void
    {
        // Arrange

        DB::shouldReceive('select')->once()->andThrow(new RuntimeException(str_repeat('x', 500)));

        $check = new DatabaseHealthCheck;

        // Act

        $result = $check->check();

        // Assert

        $this->assertSame(255, mb_strlen((string) $result->message));
    }

    /**
     * Report Degraded when the query succeeds but exceeds the slow threshold.
     *
     * The threshold is wall-clock based, so the fake connection sleeps just
     * past it rather than the check growing an injectable clock.
     */
    #[Test]
    public function it_reports_degraded_when_the_probe_is_slow(): void
    {
        // Arrange

        DB::shouldReceive('select')->once()->andReturnUsing(function (): null {
            usleep(501_000);

            return null;
        });

        $check = new DatabaseHealthCheck;

        // Act

        $result = $check->check();

        // Assert

        $this->assertSame(SystemHealthStatus::Degraded, $result->status);
        $this->assertSame('Database Responded Slowly', $result->message);
        $this->assertGreaterThan(500, $result->responseTimeMs);
    }

    /**
     * Identify the component slug persisted to `system_health_checks`.
     */
    #[Test]
    public function it_reports_the_database_component_slug(): void
    {
        // Arrange

        $check = new DatabaseHealthCheck;

        // Act

        $component = $check->component();

        // Assert

        $this->assertSame('database', $component);
    }
}
