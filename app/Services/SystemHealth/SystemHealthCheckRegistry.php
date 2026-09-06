<?php

declare(strict_types=1);

namespace App\Services\SystemHealth;

use App\Contracts\SystemHealth\SystemHealthCheck;
use App\Providers\SystemHealthServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use UnexpectedValueException;

/**
 * Resolves every container-tagged {@see SystemHealthCheck} implementation.
 *
 * The single source of truth for "which components does this app monitor" -
 * `health:record` uses {@see all()} to run every probe; the public status
 * endpoint uses {@see components()} to list every monitored component even
 * before its first scheduled run has ever persisted a row.
 */
final class SystemHealthCheckRegistry
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new SystemHealthCheckRegistry.
     *
     * @param Application $app the container the checks are tagged against
     */
    public function __construct(
        private readonly Application $app,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve every registered System Health Check implementation.
     *
     * @return list<SystemHealthCheck> every tagged check, newly resolved
     */
    public function all(): array
    {
        $checks = [];

        foreach ($this->app->tagged(SystemHealthServiceProvider::CHECKS_TAG) as $check) {
            /*
             * The container's `tagged()` return type is untyped `iterable`, so
             * this narrows with an explicit guard rather than trusting a cast -
             * a class tagged by mistake must fail loudly here, not surface as
             * a fatal `component()` call on an unrelated object later.
             */
            if (! $check instanceof SystemHealthCheck) {
                throw new UnexpectedValueException(
                    'A Class Tagged Under `'.SystemHealthServiceProvider::CHECKS_TAG.'` Does Not Implement SystemHealthCheck',
                );
            }

            $checks[] = $check;
        }

        return $checks;
    }

    /**
     * List every monitored component's slug, without running any probe.
     *
     * @return list<string> the component slugs (e.g. `database`, `cache`, `queue`)
     */
    public function components(): array
    {
        return array_map(
            static fn (SystemHealthCheck $check): string => $check->component(),
            $this->all(),
        );
    }
}
