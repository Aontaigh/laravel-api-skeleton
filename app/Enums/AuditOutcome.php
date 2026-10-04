<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Outcome of an authentication audit event, following the Microsoft Entra
 * `result` / `resultReason` model: every audit row names the principal, the
 * credential, and whether the activity succeeded, failed, or was refused.
 */
enum AuditOutcome: string
{
    /*
    |--------------------------------------------------------------------------
    | Cases
    |--------------------------------------------------------------------------
    */

    /** The activity completed as intended. */
    case Succeeded = 'succeeded';

    /** The activity was attempted and failed (bad credentials, delivery failure). */
    case Failed = 'failed';

    /** The activity was attempted and refused before it could succeed or fail (service-account reset skip). */
    case Refused = 'refused';
}
