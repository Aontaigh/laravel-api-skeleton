<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Auth\RecordAuthAuditAction;
use App\Contracts\GeoIp\GeoIpLocator;
use App\DataTransferObjects\GeoIp\GeoIpLocation;
use App\Events\AuthEventOccurred;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

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
     * pick up a later request's address.
     *
     * Lookups fail open, and the listener enforces it rather than trusting the
     * bound implementation to stay well behaved. Location is enrichment; the row is
     * the evidence. A locator that threw would otherwise abort the write before it
     * happens, so an enrichment outage would discard authentication records
     * entirely - the one failure mode an audit trail cannot afford.
     *
     * @param  AuthEventOccurred $event the dispatched authentication event
     * @return void
     */
    public function handle(AuthEventOccurred $event): void
    {
        $this->record->execute($event->data->withLocation($this->resolveLocation($event->data->ipAddress)));
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the location for an address, degrading to null on any failure.
     *
     * @param  string|null        $ipAddress the address captured at dispatch
     * @return GeoIpLocation|null the resolved location, or null
     */
    private function resolveLocation(?string $ipAddress): ?GeoIpLocation
    {
        if ($ipAddress === null) {
            return null;
        }

        try {
            return $this->geoIpLocator->locate($ipAddress);
        } catch (Throwable) {
            /*
             * Deliberately swallowed: the audit row is persisted without a location
             * rather than lost to an enrichment failure.
             */
            return null;
        }
    }
}
