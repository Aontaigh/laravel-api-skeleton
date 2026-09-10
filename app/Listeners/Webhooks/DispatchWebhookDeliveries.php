<?php

declare(strict_types=1);

namespace App\Listeners\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Events\WebhookEventDispatched;
use App\Jobs\Webhooks\DeliverWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\ApiDateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

/**
 * Fans a domain event out to every subscribed endpoint.
 *
 * Queued so the fan-out INSERTs stay off the request hot path. Creates one
 * pending delivery row per active subscribed endpoint with its canonical
 * payload, then dispatches one delivery job per row. A permanently failed
 * fan-out lands in the failed-jobs table rather than silently dropping
 * subscriber notifications.
 */
final class DispatchWebhookDeliveries implements ShouldQueue
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
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Create one pending delivery per subscribed endpoint and queue its send.
     *
     * Endpoints match on an exact event value in their `events` array; MySQL
     * has no portable array-contains operator at this layer, so subscribed
     * endpoints are filtered in PHP after selecting active rows. Endpoint
     * volumes are Admin-managed (tens, not millions), so the scan is bounded.
     *
     * @param  WebhookEventDispatched $event the dispatched domain event
     * @return void
     */
    public function handle(WebhookEventDispatched $event): void
    {
        $occurredAt = ApiDateTime::serialize(now()) ?? now()->toIso8601String();

        $endpoints = WebhookEndpoint::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint): bool => in_array($event->event->value, $endpoint->events, true))
            ->values();

        foreach ($endpoints as $endpoint) {
            $uuid = (string) Str::uuid();

            /** @var WebhookDelivery $delivery */
            $delivery = WebhookDelivery::query()->create([
                'uuid' => $uuid,
                'webhook_endpoint_id' => $endpoint->id,
                'event' => $event->event->value,
                'payload' => [
                    'id' => $uuid,
                    'event' => $event->event->value,
                    'occurred_at' => $occurredAt,
                    'data' => $event->data,
                ],
                'status' => WebhookDeliveryStatus::Pending,
            ]);

            DeliverWebhookJob::dispatch($delivery);
        }
    }
}
