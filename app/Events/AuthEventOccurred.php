<?php

declare(strict_types=1);

namespace App\Events;

use App\DataTransferObjects\Auth\RecordAuthAuditData;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Signals that an authentication event worth auditing has occurred.
 *
 * Dispatched synchronously by the auth controllers; the queued listener is
 * what persists the audit row, keeping the DB write off the request hot path.
 */
final class AuthEventOccurred
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use Dispatchable;

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * @param RecordAuthAuditData $data the audit payload to record
     */
    public function __construct(
        public readonly RecordAuthAuditData $data,
    ) {}
}
