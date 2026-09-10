<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delivers one queued webhook delivery to its endpoint.
 *
 * Retries with exponential backoff (capped at one hour) until the attempt
 * budget is spent, then marks the row exhausted. Endpoint auto-disable lives
 * here too: after the configured consecutive-failure streak the endpoint is
 * switched off so a dead receiver stops accumulating retries.
 */
final class DeliverWebhookJob implements ShouldQueue
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** Longest backoff between attempts, in seconds (one hour). */
    private const int MAX_BACKOFF_SECONDS = 3600;

    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /**
     * The number of times the job may be attempted.
     *
     * Mirrors `api.webhook_delivery_max_attempts`: the worker-level cap is the
     * backstop when the row-level retry accounting below is ever bypassed.
     */
    public int $tries = 8;

    /**
     * The number of seconds the job may run before timing out.
     */
    public int $timeout = 30;

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new DeliverWebhookJob.
     *
     * @param WebhookDelivery $delivery the delivery row to send
     */
    public function __construct(
        public readonly WebhookDelivery $delivery,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Send the delivery, recording the outcome on its row.
     *
     * A terminally failed row (budget spent) is marked exhausted rather than
     * deleted: the deliveries index is the audit trail integrators debug
     * against. Non-2xx responses and transport errors both count as failures.
     * Receiving is at-least-once (a worker may die after the POST but before
     * the row update), so receivers must treat `Webhook-Id` as an idempotency
     * key - documented on the endpoint.
     *
     * @param  WebhookSigner $signer the payload signer
     * @return void
     */
    public function handle(WebhookSigner $signer): void
    {
        /** @var WebhookDelivery|null $delivery */
        $delivery = WebhookDelivery::query()->find($this->delivery->id);

        if ($delivery === null) {
            return;
        }

        $endpoint = $delivery->endpoint;

        /*
         * A missing endpoint row is impossible here: the foreign key cascades
         * delivery rows with their endpoint, and the fresh `find()` above
         * would already have returned null. Only the active flag is checked.
         */
        if (! $endpoint->is_active) {
            $delivery->forceFill(['status' => WebhookDeliveryStatus::Exhausted])->save();

            return;
        }

        $timestamp = time();
        $body = $signer->encode($delivery->payload);
        $signature = $signer->sign($endpoint->secret, $delivery->uuid, $timestamp, $body);

        $delivery->forceFill(['attempts' => $delivery->attempts + 1])->save();

        try {
            /*
             * `withoutRedirecting` is part of the SSRF posture, not a nicety:
             * the URL guard screens the registered target, but a hostile
             * receiver can `302` the delivery to `http://169.254.169.254/`
             * and Guzzle would follow it from inside the network. A 3xx from
             * a machine receiver is a delivery failure, never a redirect to
             * chase (same posture as Stripe and GitHub webhooks).
             */
            $response = Http::withoutRedirecting()
                ->timeout(config()->integer('api.webhook_delivery_timeout_seconds'))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Webhook-Id' => $delivery->uuid,
                    'Webhook-Timestamp' => (string) $timestamp,
                    'Webhook-Signature' => $signature,
                    'Webhook-Event' => $delivery->event,
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            if ($response->successful()) {
                $this->markDelivered($delivery, $endpoint, $response->status());

                return;
            }

            $this->recordFailure($delivery, $endpoint, $response->status(), "Receiver Answered {$response->status()}");
        } catch (HttpClientException $exception) {
            $this->recordFailure($delivery, $endpoint, null, mb_substr($exception->getMessage(), 0, 1024));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Mark the delivery delivered and reset the endpoint's failure streak.
     *
     * @param  WebhookDelivery $delivery the delivered row
     * @param  WebhookEndpoint $endpoint the receiving endpoint
     * @param  int             $status   the receiver's HTTP status
     * @return void
     */
    private function markDelivered(WebhookDelivery $delivery, WebhookEndpoint $endpoint, int $status): void
    {
        $delivery->forceFill([
            'status' => WebhookDeliveryStatus::Delivered,
            'last_status_code' => $status,
            'last_error' => null,
            'next_retry_at' => null,
            'delivered_at' => now(),
        ])->save();

        $endpoint->forceFill(['failure_streak' => 0])->save();
    }

    /**
     * Record a failed attempt, rescheduling or exhausting the delivery.
     *
     * @param  WebhookDelivery $delivery the failed row
     * @param  WebhookEndpoint $endpoint the receiving endpoint
     * @param  int|null        $status   the receiver status, or null on transport failure
     * @param  string          $error    the bounded error detail
     * @return void
     */
    private function recordFailure(WebhookDelivery $delivery, WebhookEndpoint $endpoint, ?int $status, string $error): void
    {
        /*
         * Atomic increment: parallel delivery jobs for the same endpoint must
         * not lose each other's failures to a read-modify-write race.
         * `increment()` issues one `SET failure_streak = failure_streak + 1`
         * statement and syncs the new value back onto the model.
         */
        $streak = $endpoint->increment('failure_streak');

        if ($streak >= config()->integer('api.webhook_auto_disable_after_failures')) {
            $endpoint->forceFill(['is_active' => false, 'disabled_at' => now()]);

            Log::warning('Webhook Endpoint Auto-Disabled', [
                'webhook_endpoint_id' => $endpoint->id,
                'failure_streak' => $streak,
            ]);

            $endpoint->save();
        }

        if ($delivery->attempts >= config()->integer('api.webhook_delivery_max_attempts')) {
            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Exhausted,
                'last_status_code' => $status,
                'last_error' => $error,
                'next_retry_at' => null,
            ])->save();

            return;
        }

        $delivery->forceFill([
            'status' => WebhookDeliveryStatus::Failed,
            'last_status_code' => $status,
            'last_error' => $error,
            'next_retry_at' => now()->addSeconds($this->backoffSeconds($delivery->attempts)),
        ])->save();

        $this->release($this->backoffSeconds($delivery->attempts));
    }

    /**
     * Compute the exponential backoff delay for the given attempt number.
     *
     * @param  int $attempts the attempts recorded so far (1-based)
     * @return int the delay in seconds, capped at one hour
     */
    private function backoffSeconds(int $attempts): int
    {
        return min(self::MAX_BACKOFF_SECONDS, 60 * (2 ** max(0, $attempts - 1)));
    }
}
