<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why the application refused an otherwise valid credential.
 *
 * `AuditOutcome::Refused` answers *whether* the application declined; this
 * answers *which policy* declined. The two together are what let an incident
 * responder separate a deliberate administrative decision (a suspended service
 * account) from a misconfiguration (a client still pointed at a human owner)
 * from a data-integrity fault (an orphaned client), instead of grouping all three
 * under one indistinguishable `refused`.
 *
 * The scope is deliberately the client's eligibility to authenticate as a machine
 * credential, not refusal in general: every case here is a defect in the client or
 * its owning user, so naming the column for the broader concept would promise a
 * shared vocabulary that does not exist. Refusal paths elsewhere - a suspended
 * account signing in, a service account requesting a reset - record `refused` with
 * a null reason.
 *
 * Nullable on the audit row. Successful and failed attempts record null, because a
 * reason is only meaningful once a credential has proved out.
 */
enum ClientIneligibilityReason: string
{
    /*
    |--------------------------------------------------------------------------
    | Cases
    |--------------------------------------------------------------------------
    */

    case HumanOwned = 'human_owned';
    case SuspendedOwner = 'suspended_owner';
    case MissingOwner = 'missing_owner';
}
