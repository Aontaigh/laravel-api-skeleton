<?php

declare(strict_types=1);

namespace Tests\Unit\DataTransferObjects\SystemHealth;

use App\DataTransferObjects\SystemHealth\SystemHealthCheckResult;
use App\Enums\SystemHealthStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for the SystemHealthCheckResult DTO.
 */
#[CoversClass(SystemHealthCheckResult::class)]
final class SystemHealthCheckResultTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Hold the status, response time, and null message it was given.
     */
    #[Test]
    public function it_holds_the_probe_outcome_untouched(): void
    {
        // Arrange

        // Act

        $result = new SystemHealthCheckResult(
            status: SystemHealthStatus::Up,
            responseTimeMs: 42,
        );

        // Assert

        $this->assertSame(SystemHealthStatus::Up, $result->status);
        $this->assertSame(42, $result->responseTimeMs);
        $this->assertNull($result->message);
    }

    /**
     * Bound an over-long upstream exception message to the 255-character cap.
     *
     * The `message` column is `string(255)`; the bounding lives in the DTO so
     * no check implementation can smuggle an unbounded message past it.
     */
    #[Test]
    public function it_bounds_an_over_long_message_to_255_characters(): void
    {
        // Arrange

        $message = str_repeat('a', 400);

        // Act

        $result = new SystemHealthCheckResult(
            status: SystemHealthStatus::Down,
            message: $message,
        );

        // Assert

        $this->assertSame(255, mb_strlen((string) $result->message));
        $this->assertSame(str_repeat('a', 255), $result->message);
    }

    /**
     * Keep a message already within the cap unchanged.
     */
    #[Test]
    public function it_keeps_a_message_within_the_cap_unchanged(): void
    {
        // Arrange

        $message = 'Database Responded Slowly';

        // Act

        $result = new SystemHealthCheckResult(
            status: SystemHealthStatus::Degraded,
            message: $message,
        );

        // Assert

        $this->assertSame($message, $result->message);
    }
}
