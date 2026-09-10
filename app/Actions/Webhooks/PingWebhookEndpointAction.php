<?php

declare(strict_types=1);

namespace App\Actions\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Jobs\Webhooks\DeliverWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\ApiDateTime;
use Illuminate\Support\Str;

/**
 * Queues a synthetic ping delivery so integrators can verify their receiver.
 */
final class PingWebhookEndpointAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Create a `webhook.ping` delivery row and queue its send.
     *
     * The ping is a synthetic event outside the `WebhookEvent` catalog: it
     * proves signing, transport, and receiver verification end to end without
     * fabricating a domain event that never happened. It travels the exact
     * production path - pending row, signed job, retries, history.
     *
     * @example
     * app(PingWebhookEndpointAction::class)->execute($endpoint);
     *
     * @param  WebhookEndpoint $endpoint the endpoint to ping
     * @return WebhookDelivery the queued ping delivery row
     */
    public function execute(WebhookEndpoint $endpoint): WebhookDelivery
    {
        $uuid = (string) Str::uuid();

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::query()->create([
            'uuid' => $uuid,
            'webhook_endpoint_id' => $endpoint->id,
            'event' => WebhookEvent::PING,
            'payload' => [
                'id' => $uuid,
                'event' => WebhookEvent::PING,
                'occurred_at' => ApiDateTime::serialize(now()) ?? now()->toIso8601String(),
                'data' => ['message' => 'Webhook Ping'],
            ],
            'status' => WebhookDeliveryStatus::Pending,
        ]);

        DeliverWebhookJob::dispatch($delivery);

        return $delivery->refresh();
    }
}
