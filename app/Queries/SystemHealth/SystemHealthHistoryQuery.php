<?php

declare(strict_types=1);

namespace App\Queries\SystemHealth;

use App\Enums\SystemHealthStatus;
use App\Models\SystemHealthCheck;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use stdClass;

/**
 * Aggregates recorded {@see SystemHealthCheck} rows for the public status endpoint.
 *
 * Every method aggregates in the database (`groupBy` / `selectRaw`) rather
 * than pulling raw check rows into PHP - a component checked every five
 * minutes for 90 days is up to ~26,000 rows, and this query must never load
 * them all to compute a handful of daily percentages. The only client-side
 * work is filling calendar-day holes in `history()`, which is bounded by the
 * validated `days` window (at most 90 slots) and never touches check rows.
 */
final class SystemHealthHistoryQuery
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Get the latest recorded row for every component, keyed by component.
     *
     * Implemented as a self-join against a per-component `MAX(checked_at)`
     * subquery (the standard "latest row per group" SQL shape) so this is one
     * query regardless of table size - never `SystemHealthCheck::all()`
     * followed by a PHP-side `groupBy()`.
     *
     * @return Collection<string, SystemHealthCheck> component slug to its latest row
     */
    public function currentStatuses(): Collection
    {
        $latestPerComponent = SystemHealthCheck::query()
            ->selectRaw('component, max(checked_at) as latest_checked_at')
            ->groupBy('component');

        /** @var Collection<int, SystemHealthCheck> $rows */
        $rows = SystemHealthCheck::query()
            ->joinSub($latestPerComponent, 'latest', function (JoinClause $join): void {
                $join->on('system_health_checks.component', '=', 'latest.component')
                    ->on('system_health_checks.checked_at', '=', 'latest.latest_checked_at');
            })
            ->select('system_health_checks.*')
            ->get();

        return $rows->keyBy(fn (SystemHealthCheck $check): string => $check->component);
    }

    /**
     * Build the daily uptime history for one component over the last N days.
     *
     * One row per calendar day (UTC) in `[today - days + 1, today]`, always -
     * a day with no recorded checks still appears, with `uptime_percentage`
     * and `status` both `null` rather than being omitted or fabricated as Up.
     *
     * **Degraded weighting:** a day's `uptime_percentage` counts `Up` as 1,
     * `Down` as 0, and `Degraded` as 0.5. Degraded means the component
     * answered but was impaired (see {@see \App\Services\SystemHealth\Checks\DatabaseHealthCheck}'s
     * slow-response threshold) - neither "fully available" nor "fully
     * unavailable" to an end user, so counting it as either extreme would
     * misrepresent the day. Half credit is the simplest defensible midpoint;
     * a status page that needs a different weighting (e.g. per-minute time
     * actually spent Degraded) can revisit this without changing the shape
     * of the response.
     *
     * A day's discrete `status` is independent of the percentage: it is the
     * *worst* status observed that day (Down beats Degraded beats Up),
     * matching how Statuspage-style bars are conventionally coloured - a
     * single bad reading colours the whole day's bar, even if it was
     * otherwise 99% healthy.
     *
     * @param  string                                                                        $component the component slug to aggregate
     * @param  int                                                                           $days      the window size in days (1-90; callers validate the bound)
     * @return list<array{date: string, uptime_percentage: float|null, status: string|null}> one entry per calendar day, oldest first
     */
    public function history(string $component, int $days): array
    {
        [$start, $end] = $this->windowBounds($days);

        /*
         * A single SQL `groupBy(date)` per component - the weighted sum and
         * worst-severity case expressions run entirely in the database, and
         * PHP only ever sees one aggregate row per day that has data.
         */
        /** @var Collection<string, stdClass> $rowsByDay */
        $rowsByDay = SystemHealthCheck::query()
            ->where('component', $component)
            ->whereBetween('checked_at', [$start, $end])
            ->toBase()
            ->selectRaw("
                date(checked_at) as day,
                sum(case status when 'up' then 1 when 'degraded' then 0.5 else 0 end) / count(*) * 100 as uptime_percentage,
                max(case status when 'down' then 3 when 'degraded' then 2 when 'up' then 1 else 0 end) as worst_severity
            ")
            ->groupByRaw('date(checked_at)')
            ->get()
            ->keyBy(fn (stdClass $row): string => (string) $row->day);

        $history = [];
        $cursor = $start->copy();
        $lastDay = $end->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($lastDay)) {
            $dateKey = $cursor->toDateString();
            $row = $rowsByDay->get($dateKey);

            $history[] = [
                'date' => $dateKey,
                'uptime_percentage' => $row === null ? null : round((float) $row->uptime_percentage, 1),
                'status' => $row === null ? null : $this->statusFromSeverity((int) $row->worst_severity)->value,
            ];

            $cursor = $cursor->addDay();
        }

        return $history;
    }

    /**
     * Compute the single aggregate uptime percentage for a component's window.
     *
     * A genuine whole-window database aggregate, not an average of the
     * (unevenly weighted) per-day figures from {@see history()} - a day with
     * one check and a day with two hundred would otherwise count equally.
     *
     * @param  string     $component the component slug to aggregate
     * @param  int        $days      the window size in days (1-90; callers validate the bound)
     * @return float|null the uptime percentage, or null when no checks were recorded in the window
     */
    public function uptimePercentage(string $component, int $days): ?float
    {
        [$start, $end] = $this->windowBounds($days);

        $row = SystemHealthCheck::query()
            ->where('component', $component)
            ->whereBetween('checked_at', [$start, $end])
            ->toBase()
            ->selectRaw("
                sum(case status when 'up' then 1 when 'degraded' then 0.5 else 0 end) as up_weight,
                count(*) as total
            ")
            ->first();

        if ($row === null || (int) $row->total === 0) {
            return null;
        }

        return round(((float) $row->up_weight / (int) $row->total) * 100, 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the inclusive UTC window `[today - days + 1, today]` as
     * start-of-day / end-of-day boundaries.
     *
     * @param  int                         $days the window size in days
     * @return array{0: Carbon, 1: Carbon} the start and end boundaries, inclusive
     */
    private function windowBounds(int $days): array
    {
        $end = Carbon::now('UTC')->endOfDay();
        $start = $end->copy()->subDays($days - 1)->startOfDay();

        return [$start, $end];
    }

    /**
     * Map a day's worst numeric severity back to its status value.
     *
     * @param  int                $severity 1 (Up), 2 (Degraded), or 3 (Down) - see the `case` expression in {@see history()}
     * @return SystemHealthStatus the matching status
     */
    private function statusFromSeverity(int $severity): SystemHealthStatus
    {
        return match ($severity) {
            3 => SystemHealthStatus::Down,
            2 => SystemHealthStatus::Degraded,
            default => SystemHealthStatus::Up,
        };
    }
}
