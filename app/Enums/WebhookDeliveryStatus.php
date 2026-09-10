<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle states of a single outbound webhook delivery.
 */
enum WebhookDeliveryStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Exhausted = 'exhausted';

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Every delivery status value the index filter accepts.
     *
     * @return list<string> the filterable status identifiers
     */
    public static function values(): array
    {
        return array_map(
            static fn (WebhookDeliveryStatus $status): string => $status->value,
            self::cases(),
        );
    }
}
