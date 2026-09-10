<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\WebhookEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Signals that a domain event should be fanned out to webhook endpoints.
 *
 * Dispatched synchronously by resource controllers after the write commits;
 * the queued listener creates one delivery row per subscribed endpoint off
 * the request hot path.
 */
final class WebhookEventDispatched
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
     * Create a new WebhookEventDispatched event.
     *
     * @param WebhookEvent         $event the domain event that occurred
     * @param array<string, mixed> $data  the event payload (identifiers and display fields only, never secrets)
     */
    public function __construct(
        public readonly WebhookEvent $event,
        public readonly array $data,
    ) {}
}
