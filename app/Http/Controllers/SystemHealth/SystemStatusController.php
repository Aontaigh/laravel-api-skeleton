<?php

declare(strict_types=1);

namespace App\Http\Controllers\SystemHealth;

use App\Enums\SystemHealthStatus;
use App\Http\Requests\SystemHealth\ShowSystemStatusRequest;
use App\Models\SystemHealthCheck;
use App\Queries\SystemHealth\SystemHealthHistoryQuery;
use App\Services\SystemHealth\SystemHealthCheckRegistry;
use App\Support\ApiDateTime;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Public, unauthenticated status page endpoint.
 *
 * Follows {@see \App\Http\Controllers\Api\ShowHealthController}'s precedent:
 * a hand-built array via `ApiResponse::success()` rather than an API Resource,
 * because the response is an aggregate over every monitored component - there
 * is no single Eloquent model instance being shown. `GET /health` remains the
 * load-balancer probe; this endpoint is the human-facing status page.
 */
final class SystemStatusController
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** History window when the caller omits `days`. */
    private const int DEFAULT_DAYS = 90;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return the current status and uptime history for every monitored component.
     *
     * @param  ShowSystemStatusRequest   $request  the validated request
     * @param  SystemHealthCheckRegistry $registry the probe registry
     * @param  SystemHealthHistoryQuery  $query    the aggregation query
     * @return JsonResponse              the standardised success envelope
     */
    public function __invoke(
        ShowSystemStatusRequest $request,
        SystemHealthCheckRegistry $registry,
        SystemHealthHistoryQuery $query,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

        $days = $request->safe()->integer('days', self::DEFAULT_DAYS);

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        |
        | `currentStatuses()` is fetched once and reused for every component
        | below (and for the overall-status reduction) - not re-queried per
        | component, which would turn a fixed-size component list into an N+1.
        |
        */

        $currentStatuses = $query->currentStatuses();

        $components = array_map(
            fn (string $component): array => $this->buildComponentPayload($component, $days, $currentStatuses, $query),
            $registry->components(),
        );

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: [
                'monitoring_active' => $currentStatuses->isNotEmpty(),
                'overall_status' => $this->overallStatus($currentStatuses)->value,
                'components' => $components,
            ],
            message: 'System Status Retrieved Successfully',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Build one component's response entry.
     *
     * @param  string                                $component       the component slug
     * @param  int                                   $days            the validated history window
     * @param  Collection<string, SystemHealthCheck> $currentStatuses every component's latest row, keyed by component
     * @param  SystemHealthHistoryQuery              $query           the aggregation query
     * @return array<string, mixed>                  the component's `components[]` entry
     */
    private function buildComponentPayload(
        string $component,
        int $days,
        Collection $currentStatuses,
        SystemHealthHistoryQuery $query,
    ): array {
        $current = $currentStatuses->get($component);

        return [
            'component' => $component,
            'label' => Str::headline($component),
            'status' => $current?->status->value,
            'checked_at' => ApiDateTime::serialize($current?->checked_at),
            'uptime_percentage' => $query->uptimePercentage($component, $days),
            'history' => $query->history($component, $days),
        ];
    }

    /**
     * Reduce every component's current status to the single worst reading.
     *
     * A component that has never recorded a check (fresh install, or before
     * `health:record`'s first scheduled run) contributes no reading rather
     * than counting as Down - the absence of monitoring data is not evidence
     * of an outage. When nothing has reported yet, the page defaults to Up
     * rather than showing a false alarm with no supporting detail.
     *
     * @param  Collection<string, SystemHealthCheck> $currentStatuses every component's latest row, keyed by component
     * @return SystemHealthStatus                    the worst status across every component with a reading
     */
    private function overallStatus(Collection $currentStatuses): SystemHealthStatus
    {
        return $currentStatuses
            ->map(fn (SystemHealthCheck $check): SystemHealthStatus => $check->status)
            ->reduce(
                fn (?SystemHealthStatus $worst, SystemHealthStatus $status): SystemHealthStatus => $worst === null || $status->severity() > $worst->severity()
                    ? $status
                    : $worst,
            ) ?? SystemHealthStatus::Up;
    }
}
