<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Domain events that can be delivered to webhook endpoints.
 *
 * Values are dotted, version-stable identifiers sent verbatim as the
 * `event` field of every delivery payload and stored on the delivery row.
 */
enum WebhookEvent: string
{
    case UserCreated = 'user.created';
    case UserSuspended = 'user.suspended';
    case UserUnsuspended = 'user.unsuspended';
    case TeamCreated = 'team.created';
    case TeamUpdated = 'team.updated';
    case TeamDeleted = 'team.deleted';
    case UserDeleted = 'user.deleted';

    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * Synthetic event for test pings.
     *
     * Outside the `cases()` catalog on purpose: nobody subscribes to it (pings
     * target one endpoint directly), but delivery rows carry it, so filters
     * and the OpenAPI contract accept the value wherever an event identifier
     * is filtered or documented.
    /**
     * Synthetic event for test pings.
     * Outside the `cases()` catalog on purpose: nobody subscribes to it (pings
     * target one endpoint directly), but delivery rows carry it, so filters
     * and the OpenAPI contract accept the value wherever an event identifier
     * is filtered or documented.
     */
    public const string PING = 'webhook.ping';

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Every event value a subscription may include.
     *
     * @return list<string> the subscribable event identifiers
     */
    public static function values(): array
    {
        return array_map(
            static fn (WebhookEvent $event): string => $event->value,
            self::cases(),
        );
    }
}
