<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Jobs\Webhooks\DeliverWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookSigner;
use App\Services\Webhooks\WebhookUrlGuard;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\Concerns\FakesWebhookDns;
use Tests\TestCase;

/**
 * Feature tests for webhook delivery sends against faked receivers.
 */
#[CoversClass(DeliverWebhookJob::class)]
#[CoversClass(WebhookSigner::class)]
final class DeliverWebhookJobTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use FakesWebhookDns;
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup / Teardown
    |--------------------------------------------------------------------------
    */

    /**
     * Seed roles for factory-built owners and fake the URL guard's DNS.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->fakeWebhookDns([
            'example.com' => ['93.184.216.34'],
            'public.example' => ['93.184.216.34'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Mark the delivery delivered and reset the endpoint streak on 2xx.
     */
    #[Test]
    public function it_marks_delivered_and_resets_the_streak_on_success(): void
    {
        // Arrange

        Http::fake(['*' => Http::response('ok', 200)]);

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create(['failure_streak' => 3]);

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create();

        // Act

        (new DeliverWebhookJob($delivery))->handle(app(WebhookSigner::class), app(WebhookUrlGuard::class));

        // Assert

        $fresh = $delivery->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(WebhookDeliveryStatus::Delivered, $fresh->status);
        $this->assertSame(200, $fresh->last_status_code);
        $this->assertSame(1, $fresh->attempts);
        $this->assertNotNull($fresh->delivered_at);
        $this->assertSame(0, $endpoint->fresh()?->failure_streak);
    }

    /**
     * Send the exact canonical body the signature covers.
     *
     * Recomputes the signature over the sent body: proves the bytes signed
     * are the bytes transmitted, not just that a header exists.
     */
    #[Test]
    public function it_sends_the_signed_canonical_body(): void
    {
        // Arrange

        Http::fake(['*' => Http::response('ok', 200)]);

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create();

        // Act

        (new DeliverWebhookJob($delivery))->handle(app(WebhookSigner::class), app(WebhookUrlGuard::class));

        // Assert

        Http::assertSent(function (Request $httpRequest) use ($endpoint, $delivery): bool {
            $signatureHeaders = $httpRequest->header('Webhook-Signature');
            $signature = is_string($signatureHeaders[0] ?? null) ? $signatureHeaders[0] : '';

            $timestampHeaders = $httpRequest->header('Webhook-Timestamp');
            $timestamp = is_numeric($timestampHeaders[0] ?? null) ? (int) $timestampHeaders[0] : 0;

            return $httpRequest->url() === $endpoint->url
                && $httpRequest->header('Webhook-Event') === [$delivery->event]
                && app(WebhookSigner::class)->verify(
                    $endpoint->secret,
                    $delivery->uuid,
                    $timestamp,
                    $httpRequest->body(),
                    $signature,
                );
        });
    }

    /**
     * Reschedule a failed delivery with backoff instead of dropping it.
     */
    #[Test]
    public function it_reschedules_a_failed_delivery_with_backoff(): void
    {
        // Arrange

        Http::fake(['*' => Http::response('busy', 500)]);

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create();

        // Act

        (new DeliverWebhookJob($delivery))->handle(app(WebhookSigner::class), app(WebhookUrlGuard::class));

        // Assert

        $fresh = $delivery->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(WebhookDeliveryStatus::Failed, $fresh->status);
        $this->assertSame(500, $fresh->last_status_code);
        $this->assertSame(1, $fresh->attempts);
        $this->assertNotNull($fresh->next_retry_at);
        $this->assertSame(1, $endpoint->fresh()?->failure_streak);
    }

    /**
     * Exhaust the delivery once the attempt budget is spent.
     */
    #[Test]
    public function it_exhausts_the_delivery_when_the_budget_is_spent(): void
    {
        // Arrange

        config(['api.webhook_delivery_max_attempts' => 1]);
        Http::fake(['*' => Http::response('busy', 500)]);

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create();

        // Act

        (new DeliverWebhookJob($delivery))->handle(app(WebhookSigner::class), app(WebhookUrlGuard::class));

        // Assert

        $fresh = $delivery->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(WebhookDeliveryStatus::Exhausted, $fresh->status);
        $this->assertNull($fresh->next_retry_at);
    }

    /**
     * Auto-disable the endpoint after the configured consecutive failures.
     */
    #[Test]
    public function it_disables_the_endpoint_after_the_failure_streak(): void
    {
        // Arrange

        config(['api.webhook_auto_disable_after_failures' => 1]);
        Http::fake(['*' => Http::response('busy', 500)]);

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create();

        // Act

        (new DeliverWebhookJob($delivery))->handle(app(WebhookSigner::class), app(WebhookUrlGuard::class));

        // Assert

        $fresh = $endpoint->fresh();

        $this->assertNotNull($fresh);
        $this->assertFalse($fresh->is_active);
        $this->assertNotNull($fresh->disabled_at);
    }

    /**
     * Skip sending to a disabled endpoint, exhausting the row instead.
     */
    #[Test]
    public function it_exhausts_instead_of_sending_to_a_disabled_endpoint(): void
    {
        // Arrange

        Http::fake(['*' => Http::response('ok', 200)]);

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->disabled()->create();

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create();

        // Act

        (new DeliverWebhookJob($delivery))->handle(app(WebhookSigner::class), app(WebhookUrlGuard::class));

        // Assert

        $this->assertSame(WebhookDeliveryStatus::Exhausted, $delivery->fresh()?->status);

        Http::assertNothingSent();
    }

    /**
     * Ignore a delivery row that no longer exists.
     */
    #[Test]
    public function it_ignores_a_missing_delivery_row(): void
    {
        // Arrange

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::factory()->create();
        $delivery->delete();

        // Act

        (new DeliverWebhookJob($delivery))->handle(app(WebhookSigner::class), app(WebhookUrlGuard::class));

        // Assert

        Http::assertNothingSent();
    }

    /**
     * Never follow redirects from a receiver.
     *
     * The SSRF screen validates the registered URL, but a hostile receiver
     * can `302` the delivery to `http://169.254.169.254/…` - following the
     * redirect turns every domain event into an internal-network probe from
     * inside the network. Webhook receivers are machine endpoints: a 3xx is
     * a delivery failure, never a redirect to chase (same posture as Stripe
     * and GitHub).
     */
    #[Test]
    public function it_never_follows_redirects_from_the_receiver(): void
    {
        // Arrange

        Http::fake([
            'public.example/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        ]);

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create(['url' => 'https://public.example/hooks']);

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create();

        // Act

        (new DeliverWebhookJob($delivery))->handle(app(WebhookSigner::class), app(WebhookUrlGuard::class));

        // Assert

        /*
         * Exactly one outbound request: the delivery itself. A second fake hit
         * would be the followed redirect probing the metadata service.
         */
        Http::assertSentCount(1);
        Http::assertNotSent(function (Request $httpRequest): bool {
            return str_starts_with($httpRequest->url(), 'http://169.254.169.254');
        });
        $this->assertSame(WebhookDeliveryStatus::Failed, $delivery->fresh()?->status);
    }

    /**
     * Lose no failures when two deliveries for one endpoint record concurrently.
     *
     * A sequential test cannot reproduce the race: each handle() lazy-loads a
     * fresh endpoint, so single-threaded runs never hold stale copies. This
     * test drives `recordFailure()` directly with two deliberately stale model
     * instances, the state two parallel workers would genuinely hold - the
     * read-modify-write implementation lost the first failure (both wrote
     * streak 1); the atomic increment keeps both (2).
     */
    #[Test]
    public function it_loses_no_failures_when_deliveries_record_concurrently(): void
    {
        // Arrange

        /*
         * Threshold above 2 so neither failure disables the endpoint: the
         * streak itself is what this test observes.
         */
        config(['api.webhook_auto_disable_after_failures' => 99]);
        Http::fake(['*' => Http::response('busy', 500)]);

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        /** @var WebhookDelivery $firstDelivery */
        $firstDelivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create();
        /** @var WebhookDelivery $secondDelivery */
        $secondDelivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create();

        /*
         * Simulate two workers that resolved the endpoint before either
         * failure was recorded: both now hold a stale copy with streak 0.
         */
        $staleCopyForFirst = $endpoint->fresh();
        $staleCopyForSecond = $endpoint->fresh();

        $job = new DeliverWebhookJob($firstDelivery);
        $recordFailure = new ReflectionMethod($job, 'recordFailure');

        // Act

        $recordFailure->invoke(
            $job,
            $firstDelivery,
            $staleCopyForFirst,
            500,
            'Receiver Answered 500',
        );
        $recordFailure->invoke(
            $job,
            $secondDelivery,
            $staleCopyForSecond,
            500,
            'Receiver Answered 500',
        );

        // Assert

        $this->assertSame(2, $endpoint->fresh()?->failure_streak);
    }

    /**
     * Persist the auto-disable the moment the streak threshold is crossed.
     */
    #[Test]
    public function it_persists_the_disable_immediately_when_the_streak_is_crossed(): void
    {
        // Arrange

        config(['api.webhook_auto_disable_after_failures' => 1]);
        Http::fake(['*' => Http::response('busy', 500)]);

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create();

        // Act

        (new DeliverWebhookJob($delivery))->handle(app(WebhookSigner::class), app(WebhookUrlGuard::class));

        // Assert

        $fresh = $endpoint->fresh();

        $this->assertNotNull($fresh);
        $this->assertFalse($fresh->is_active);
        $this->assertNotNull($fresh->disabled_at);
        $this->assertSame(1, $fresh->failure_streak);
    }

    /**
     * Auto-disable at the configured threshold, not at the increment's row count.
     *
     * The default threshold is 10. The previous implementation compared the
     * affected-row count (`1`) to the threshold, so a dead receiver was never
     * disabled. This test starts one failure short of the default threshold.
     */
    #[Test]
    public function it_auto_disables_at_the_configured_threshold(): void
    {
        // Arrange

        config(['api.webhook_auto_disable_after_failures' => 10]);
        Http::fake(['*' => Http::response('unavailable', 503)]);

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create(['failure_streak' => 9]);

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create();

        // Act

        (new DeliverWebhookJob($delivery))->handle(app(WebhookSigner::class), app(WebhookUrlGuard::class));

        // Assert

        $fresh = $endpoint->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(10, $fresh->failure_streak);
        $this->assertFalse($fresh->is_active);
        $this->assertNotNull($fresh->disabled_at);
    }

    /**
     * Keep the endpoint active one failure below the configured threshold.
     */
    #[Test]
    public function it_keeps_the_endpoint_active_below_the_threshold(): void
    {
        // Arrange

        config(['api.webhook_auto_disable_after_failures' => 10]);
        Http::fake(['*' => Http::response('unavailable', 503)]);

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create(['failure_streak' => 8]);

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create();

        // Act

        (new DeliverWebhookJob($delivery))->handle(app(WebhookSigner::class), app(WebhookUrlGuard::class));

        // Assert

        $fresh = $endpoint->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(9, $fresh->failure_streak);
        $this->assertTrue($fresh->is_active);
        $this->assertNull($fresh->disabled_at);
    }

    /**
     * Refuse delivery when the URL fails the delivery-time SSRF screen.
     *
     * The guard runs on create/update, but DNS can change between storage and
     * delivery. Re-screening at send time closes the rebinding window.
     */
    #[Test]
    public function it_refuses_to_deliver_when_the_url_fails_the_delivery_time_screen(): void
    {
        // Arrange

        $this->fakeWebhookDns(['example.com' => ['127.0.0.1']]);

        Http::fake(['*' => Http::response('ok', 200)]);

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create();

        // Act

        (new DeliverWebhookJob($delivery))->handle(app(WebhookSigner::class), app(WebhookUrlGuard::class));

        // Assert

        Http::assertNothingSent();

        $fresh = $delivery->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(WebhookDeliveryStatus::Failed, $fresh->status);
        $this->assertSame('Webhook URL Failed Delivery-Time SSRF Screen', $fresh->last_error);
        $this->assertSame(1, $endpoint->fresh()?->failure_streak);
    }

    /**
     * Do not resend a webhook whose row is already delivered.
     */
    #[Test]
    public function it_does_not_resend_an_already_delivered_delivery(): void
    {
        // Arrange

        Http::fake(['*' => Http::response('ok', 200)]);

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
            'status' => WebhookDeliveryStatus::Delivered,
            'attempts' => 1,
            'delivered_at' => now(),
        ]);

        // Act

        (new DeliverWebhookJob($delivery))->handle(app(WebhookSigner::class), app(WebhookUrlGuard::class));

        // Assert

        Http::assertNothingSent();

        $fresh = $delivery->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(WebhookDeliveryStatus::Delivered, $fresh->status);
        $this->assertSame(1, $fresh->attempts);
    }

    /**
     * Leave a delivered row delivered when a duplicate attempt dies.
     */
    #[Test]
    public function it_does_not_exhaust_a_delivered_row_when_a_duplicate_fails(): void
    {
        // Arrange

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
            'status' => WebhookDeliveryStatus::Delivered,
            'attempts' => 1,
            'delivered_at' => now(),
        ]);

        // Act

        (new DeliverWebhookJob($delivery))->failed(null);

        // Assert

        $fresh = $delivery->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(WebhookDeliveryStatus::Delivered, $fresh->status);
        $this->assertNotNull($fresh->delivered_at);
    }
}
