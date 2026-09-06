<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\SystemHealth\Checks\CacheHealthCheck;
use App\Services\SystemHealth\Checks\DatabaseHealthCheck;
use App\Services\SystemHealth\Checks\QueueHealthCheck;
use Illuminate\Support\ServiceProvider;

/**
 * Tags every `SystemHealthCheck` implementation for {@see \App\Services\SystemHealth\SystemHealthCheckRegistry}.
 *
 * A plain `bind(SystemHealthCheck::class, ...)` only keeps the last binding -
 * this app has three simultaneous implementations of the one contract, so a
 * container tag (not a single bind) is the correct mechanism: it lets the
 * registry resolve "every implementation" as a collection, and lets adding a
 * fourth check (e.g. an outbound-mail probe) touch only this provider.
 */
final class SystemHealthServiceProvider extends ServiceProvider
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** The container tag every SystemHealthCheck implementation is registered under. */
    public const string CHECKS_TAG = 'system-health.checks';

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Register SystemHealth container bindings.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->tag([
            DatabaseHealthCheck::class,
            CacheHealthCheck::class,
            QueueHealthCheck::class,
        ], self::CHECKS_TAG);
    }
}
