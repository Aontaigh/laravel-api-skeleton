<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Auth\RecordAuthAuditAction;
use App\Contracts\GeoIp\GeoIpLocator;
use App\Events\AuthEventOccurred;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Persists an authentication audit event off the request hot path.
 *
 * Queued so the audit INSERT does not add a synchronous DB write to login,
 * register, or token exchange. On a queue failure the event is retried per the
 * limits below; a permanently failed write lands in the failed-jobs table for
 * inspection rather than silently dropping an audit record.
 */
final class RecordAuthAuditLog implements ShouldQueue
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /**
     * The number of times the queued listener may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the queued listener may run before timing out.
     */
    public int $timeout = 30;

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new RecordAuthAuditLog listener.
     *
     * @param RecordAuthAuditAction $record       the audit persistence Action
     * @param GeoIpLocator          $geoIpLocator the fail-open city/country lookup
     */
    public function __construct(
        private readonly RecordAuthAuditAction $record,
        private readonly GeoIpLocator $geoIpLocator,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Persist the audit row for the dispatched event.
     *
     * The location is resolved here from the event payload's `ipAddress` -
     * captured at dispatch - never from `request()`, so a queued worker cannot
     * pick up a later request's address. Lookups fail open.
     *
     * @param  AuthEventOccurred $event the dispatched authentication event
     * @return void
     */
    public function handle(AuthEventOccurred $event): void
    {
        $location = $this->geoIpLocator->locate($event->data->ipAddress);

        $this->record->execute($event->data->withLocation($location));
    }
}
