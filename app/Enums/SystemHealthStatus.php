<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The outcome of a single System Health Check run for one component.
 */
enum SystemHealthStatus: string
{
    case Up = 'up';
    case Degraded = 'degraded';
    case Down = 'down';

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Get the human-readable Title-Case label for the status.
     *
     * @return string the display label
     */
    public function label(): string
    {
        return match ($this) {
            self::Up => 'Up',
            self::Degraded => 'Degraded',
            self::Down => 'Down',
        };
    }

    /**
     * Get the severity ranking used to resolve an overall status.
     *
     * Higher is worse. `SystemStatusController` reduces every component's
     * current status to the single worst reading for `overall_status` -
     * `Down` must always outrank `Degraded`, which must always outrank `Up`,
     * regardless of enum declaration order.
     *
     * @return int the severity rank; higher is worse
     */
    public function severity(): int
    {
        return match ($this) {
            self::Up => 0,
            self::Degraded => 1,
            self::Down => 2,
        };
    }
}
