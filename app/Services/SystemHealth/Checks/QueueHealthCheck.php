<?php

declare(strict_types=1);

namespace App\Services\SystemHealth\Checks;

use App\Contracts\SystemHealth\SystemHealthCheck;
use App\DataTransferObjects\SystemHealth\SystemHealthCheckResult;
use App\Enums\SystemHealthStatus;
use App\Services\SystemHealth\Checks\Concerns\MeasuresHealthCheckTiming;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Probes the default queue connection without ever dispatching a real job.
 */
final class QueueHealthCheck implements SystemHealthCheck
{
    use MeasuresHealthCheckTiming;

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
        return 'queue';
    }

    /**
     * Report Up unconditionally for `sync`, otherwise resolve the connection.
     *
     * `sync` runs jobs inline in the same process - there is no separate
     * worker or broker to probe, so reporting anything but Up would just be
     * measuring PHP's own uptime under another name. For every other driver,
     * resolving the connection (via the queue manager) is deliberately the
     * ceiling of what this probe does: it proves the broker config resolves
     * and, for drivers backed by an eager connection (database, Redis), that
     * the underlying connection opens - without ever enqueueing a throwaway
     * job that a real worker could pick up and act on.
     *
     * @return SystemHealthCheckResult the probe outcome
     */
    public function check(): SystemHealthCheckResult
    {
        $driver = config()->string('queue.default');

        if ($driver === 'sync') {
            return new SystemHealthCheckResult(status: SystemHealthStatus::Up, responseTimeMs: 0);
        }

        $startedAt = microtime(true);

        try {
            Queue::connection($driver);
        } catch (Throwable $exception) {
            return new SystemHealthCheckResult(
                status: SystemHealthStatus::Down,
                responseTimeMs: $this->elapsedMs($startedAt),
                message: $exception->getMessage(),
            );
        }

        return new SystemHealthCheckResult(
            status: SystemHealthStatus::Up,
            responseTimeMs: $this->elapsedMs($startedAt),
        );
    }
}
