<?php

declare(strict_types=1);

namespace Tests\Unit\Services\SystemHealth\Checks;

use App\DataTransferObjects\SystemHealth\SystemHealthCheckResult;
use App\Enums\SystemHealthStatus;
use App\Services\SystemHealth\Checks\QueueHealthCheck;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\UnitTestCase;

/**
 * Unit tests for the QueueHealthCheck probe.
 */
#[CoversClass(QueueHealthCheck::class)]
#[CoversClass(SystemHealthCheckResult::class)]
final class QueueHealthCheckTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Report Up unconditionally for the sync driver.
     *
     * `sync` runs jobs inline - there is no broker to probe, and resolving the
     * connection would only measure PHP's own uptime.
     */
    #[Test]
    public function it_reports_up_unconditionally_for_the_sync_driver(): void
    {
        // Arrange

        config(['queue.default' => 'sync']);

        Queue::shouldReceive('connection')->never();

        $check = new QueueHealthCheck;

        // Act

        $result = $check->check();

        // Assert

        $this->assertSame(SystemHealthStatus::Up, $result->status);
        $this->assertSame(0, $result->responseTimeMs);
        $this->assertNull($result->message);
    }

    /**
     * Report Up when a non-sync driver's connection resolves.
     */
    #[Test]
    public function it_reports_up_when_a_non_sync_connection_resolves(): void
    {
        // Arrange

        config(['queue.default' => 'database']);

        Queue::shouldReceive('connection')->once()->with('database')->andReturnNull();

        $check = new QueueHealthCheck;

        // Act

        $result = $check->check();

        // Assert

        $this->assertSame(SystemHealthStatus::Up, $result->status);
        $this->assertNull($result->message);
    }

    /**
     * Report Down (never throw) when the queue connection cannot be resolved.
     */
    #[Test]
    public function it_reports_down_when_the_connection_cannot_be_resolved(): void
    {
        // Arrange

        config(['queue.default' => 'redis']);

        Queue::shouldReceive('connection')->once()->with('redis')->andThrow(new RuntimeException('Redis went away'));

        $check = new QueueHealthCheck;

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

        config(['queue.default' => 'redis']);

        Queue::shouldReceive('connection')->once()->andThrow(new RuntimeException(str_repeat('x', 500)));

        $check = new QueueHealthCheck;

        // Act

        $result = $check->check();

        // Assert

        $this->assertSame(255, mb_strlen((string) $result->message));
    }

    /**
     * Identify the component slug persisted to `system_health_checks`.
     */
    #[Test]
    public function it_reports_the_queue_component_slug(): void
    {
        // Arrange

        $check = new QueueHealthCheck;

        // Act

        $component = $check->component();

        // Assert

        $this->assertSame('queue', $component);
    }
}
