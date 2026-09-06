<?php

declare(strict_types=1);

namespace App\Services\SystemHealth\Checks;

use App\Contracts\SystemHealth\SystemHealthCheck;
use App\DataTransferObjects\SystemHealth\SystemHealthCheckResult;
use App\Enums\SystemHealthStatus;
use App\Services\SystemHealth\Checks\Concerns\MeasuresHealthCheckTiming;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Probes the default cache store with a `put()`/`get()` round trip on a
 * throwaway key.
 */
final class CacheHealthCheck implements SystemHealthCheck
{
    use MeasuresHealthCheckTiming;

    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** Seconds the throwaway probe key lives for, as a backstop if `forget()` is skipped by a failure. */
    private const int PROBE_KEY_TTL_SECONDS = 10;

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
        return 'cache';
    }

    /**
     * Round-trip a random, throwaway key through the default cache store.
     *
     * @return SystemHealthCheckResult the probe outcome
     */
    public function check(): SystemHealthCheckResult
    {
        $startedAt = microtime(true);

        /*
         * Randomised per run so concurrent workers (or overlapping scheduled
         * runs) never race each other's read/write of the same key.
         */
        $key = 'system-health:cache-check:'.Str::random(32);

        try {
            Cache::put($key, true, self::PROBE_KEY_TTL_SECONDS);
            $roundTripped = Cache::get($key) === true;
            Cache::forget($key);
        } catch (Throwable $exception) {
            return new SystemHealthCheckResult(
                status: SystemHealthStatus::Down,
                responseTimeMs: $this->elapsedMs($startedAt),
                message: $exception->getMessage(),
            );
        }

        if (! $roundTripped) {
            return new SystemHealthCheckResult(
                status: SystemHealthStatus::Down,
                responseTimeMs: $this->elapsedMs($startedAt),
                message: 'Cache Round Trip Returned an Unexpected Value',
            );
        }

        return new SystemHealthCheckResult(
            status: SystemHealthStatus::Up,
            responseTimeMs: $this->elapsedMs($startedAt),
        );
    }
}
