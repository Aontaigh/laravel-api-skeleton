<?php

declare(strict_types=1);

namespace App\Actions\Sessions;

use App\Models\WebSession;
use Illuminate\Database\Eloquent\Builder;

/**
 * Stamps last-activity on the registry row for an inbound cookie session.
 */
final class TouchWebSessionActivityAction
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * Minimum minutes between last-activity writes for the same session.
     *
     * Throttle lives on `last_activity_at`, never a static map or Octane
     * worker memory: a long-lived worker must not share per-session state,
     * and the indexed UPDATE is a no-op when the row is missing, revoked, or
     * recently touched.
     */
    public const int TOUCH_INTERVAL_MINUTES = 5;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Refresh last-activity when the row is older than the throttle window.
     *
     * No location enrichment: device context is recorded at registration. A
     * single indexed update keeps the write off the hot path for recent rows.
     *
     * @param  string $sessionId the inbound Laravel session ID
     * @return void
     */
    public function execute(string $sessionId): void
    {
        WebSession::query()
            ->where('session_id', $sessionId)
            ->whereNull('revoked_at')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('last_activity_at')
                    ->orWhere(
                        'last_activity_at',
                        '<',
                        now()->subMinutes(self::TOUCH_INTERVAL_MINUTES),
                    );
            })
            ->update(['last_activity_at' => now()]);
    }
}
