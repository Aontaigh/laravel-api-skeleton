<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Auth\RecordAuthAuditAction;
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
     * @param RecordAuthAuditAction $record the audit persistence Action
     */
    public function __construct(
        private readonly RecordAuthAuditAction $record,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Persist the audit row for the dispatched event.
     *
     * @param AuthEventOccurred $event the dispatched authentication event
     */
    public function handle(AuthEventOccurred $event): void
    {
        $this->record->execute($event->data);
    }
}
