<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookSigner;
use App\Services\Webhooks\WebhookUrlGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    /**
     * Longest backoff between attempts, in seconds (one hour).
     *
     * Backoff doubles per attempt up to this ceiling, so a receiver that is down for hours is retried at a
     * steady hourly rate rather than at an unbounded interval.
     *
     * @var int
     */
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
    public int $tries;

    /**
     * The number of seconds the job may run before timing out.
     *
     * Derived from `api.webhook_delivery_timeout_seconds` plus headroom so the
     * worker never kills an in-flight HTTP send that the configured timeout
     * still allows.
     */
    public int $timeout;

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
    ) {
        $this->tries = config()->integer('api.webhook_delivery_max_attempts');
        $this->timeout = config()->integer('api.webhook_delivery_timeout_seconds') + 20;
    }

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
     * @param  WebhookSigner   $signer the payload signer
     * @param  WebhookUrlGuard $guard  re-screens the target URL at send time
     * @return void
     */
    public function handle(WebhookSigner $signer, WebhookUrlGuard $guard): void
    {
        /** @var WebhookDelivery|null $delivery */
        $delivery = WebhookDelivery::query()->find($this->delivery->id);

        if ($delivery === null) {
            return;
        }

        /*
         * A redelivered job can run after the row was already delivered (the
         * worker crashed between the send and the queue ack); resending would
         * duplicate the webhook, and a late failure would overwrite the
         * delivered state.
         */
        if ($delivery->status === WebhookDeliveryStatus::Delivered) {
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

        /*
         * Re-screen at send time: DNS can change between registration and
         * delivery, so a target that was public when saved may now resolve to
         * a private address. A failed screen is a delivery failure, never a
         * send.
         */
        if (! $guard->allows($endpoint->url)) {
            $delivery->forceFill(['attempts' => $delivery->attempts + 1])->save();

            $this->recordFailure($delivery, $endpoint, null, 'Webhook URL Failed Delivery-Time SSRF Screen');

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

    /**
     * Mark the delivery exhausted when the worker exhausts its retries.
     *
     * The row-level accounting normally marks exhaustion; this is the backstop
     * for a worker-level kill (timeout or uncaught exception) so the delivery
     * never stays silently `Failed`.
     *
     * @param  Throwable|null $exception the exception that killed the job
     * @return void
     */
    public function failed(?Throwable $exception): void
    {
        $delivery = WebhookDelivery::query()->find($this->delivery->id);

        /*
         * At-least-once queues can run the same delivery twice: a duplicate
         * attempt that dies after another worker already delivered must not
         * overwrite the delivered row with an exhausted one.
         */
        if ($delivery === null || $delivery->status === WebhookDeliveryStatus::Delivered) {
            return;
        }

        $delivery->forceFill([
            'status' => WebhookDeliveryStatus::Exhausted,
            'last_error' => $exception === null
                ? 'Webhook delivery job failed'
                : mb_substr($exception->getMessage(), 0, 1024),
            'next_retry_at' => null,
        ])->save();
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
         * statement and updates the model's attribute; its return value is the
         * affected-row count, so read the attribute for the new streak.
         */
        $endpoint->increment('failure_streak');
        $streak = $endpoint->failure_streak;

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
