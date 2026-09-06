<?php

declare(strict_types=1);

namespace App\Contracts\SystemHealth;

use App\DataTransferObjects\SystemHealth\SystemHealthCheckResult;

/**
 * A single probe against one internal component (database, cache, queue, ...).
 *
 * Kept behind an interface so `health:record` and the public status endpoint
 * can be unit-tested against a fake - and so a new component check is a new
 * class tagged in {@see \App\Providers\SystemHealthServiceProvider}, never a
 * branch added to an existing check.
 */
interface SystemHealthCheck
{
    /**
     * The stable component slug persisted to `system_health_checks.component`.
     *
     * @return string the component slug (e.g. `database`, `cache`, `queue`)
     */
    public function component(): string;

    /**
     * Run the probe and report its outcome.
     *
     * Must never throw: a failing probe reports {@see \App\Enums\SystemHealthStatus::Down}
     * with a safe, bounded message rather than letting the exception propagate
     * and take the recording command (or, for an inline check, the request)
     * down with it.
     *
     * @return SystemHealthCheckResult the probe outcome
     */
    public function check(): SystemHealthCheckResult;
}
