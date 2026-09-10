<?php

declare(strict_types=1);

namespace App\Services\SystemHealth\Checks;

use App\Contracts\SystemHealth\SystemHealthCheck;
use App\DataTransferObjects\SystemHealth\SystemHealthCheckResult;
use App\Enums\SystemHealthStatus;
use App\Services\SystemHealth\Checks\Concerns\MeasuresHealthCheckTiming;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Probes the default database connection with the lightest possible query.
 */
final class DatabaseHealthCheck implements SystemHealthCheck
{
    use MeasuresHealthCheckTiming;

    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * A successful query slower than this is reported Degraded, not Up.
     *
     * `select 1` should return in single-digit milliseconds on a healthy
     * connection; a slow-but-succeeding query is an early warning sign
     * (connection pool exhaustion, replica lag) that a binary Up/Down status
     * would hide from the public status page until the database actually
     * failed outright.
    /**
     * A successful query slower than this is reported Degraded, not Up.
     * `select 1` should return in single-digit milliseconds on a healthy
     * connection; a slow-but-succeeding query is an early warning sign
     * (connection pool exhaustion, replica lag) that a binary Up/Down status
     * would hide from the public status page until the database actually
     * failed outright.
     */
    private const int DEGRADED_THRESHOLD_MS = 500;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * The stable component slug persisted to `system_health_checks.component`.
     *
     * @return string the component slug
     */
    public function component(): string
    {
        return 'database';
    }

    /**
     * Run `select 1` against the default connection and report the outcome.
     *
     * @return SystemHealthCheckResult the probe outcome
     */
    public function check(): SystemHealthCheckResult
    {
        $startedAt = microtime(true);

        try {
            DB::select('select 1');
        } catch (Throwable $exception) {
            return new SystemHealthCheckResult(
                status: SystemHealthStatus::Down,
                responseTimeMs: $this->elapsedMs($startedAt),
                message: $exception->getMessage(),
            );
        }

        $responseTimeMs = $this->elapsedMs($startedAt);

        if ($responseTimeMs > self::DEGRADED_THRESHOLD_MS) {
            return new SystemHealthCheckResult(
                status: SystemHealthStatus::Degraded,
                responseTimeMs: $responseTimeMs,
                message: 'Database Responded Slowly',
            );
        }

        return new SystemHealthCheckResult(
            status: SystemHealthStatus::Up,
            responseTimeMs: $responseTimeMs,
        );
    }
}
