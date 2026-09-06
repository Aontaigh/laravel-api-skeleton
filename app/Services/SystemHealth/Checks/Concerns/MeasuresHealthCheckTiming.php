<?php

declare(strict_types=1);

namespace App\Services\SystemHealth\Checks\Concerns;

/**
 * Shared timing helper for {@see \App\Contracts\SystemHealth\SystemHealthCheck}
 * implementations, so each concrete check only writes the probe itself.
 */
trait MeasuresHealthCheckTiming
{
    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Compute elapsed milliseconds since a `microtime(true)` start mark.
     *
     * @param  float $startedAt the `microtime(true)` value captured before the probe
     * @return int   the elapsed time in whole milliseconds
     */
    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
