<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Actions\Auth\RecordAuthAuditAction;
use App\Contracts\GeoIp\GeoIpLocator;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\DataTransferObjects\GeoIp\GeoIpLocation;
use App\Enums\AuditOutcome;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Listeners\RecordAuthAuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Feature tests for the queued authentication audit listener.
 */
#[CoversClass(AuthEventOccurred::class)]
#[CoversClass(RecordAuthAuditLog::class)]
#[CoversClass(RecordAuthAuditAction::class)]
#[CoversClass(RecordAuthAuditData::class)]
final class RecordAuthAuditLogTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup / Teardown
    |--------------------------------------------------------------------------
    */

    /**
     * Register exactly one listener so a single dispatch cannot write duplicate rows.
     *
     * This repository relies on Laravel's listener auto-discovery, so the count below
     * is the only thing standing between a future explicit registration in a provider
     * and two audit rows per event. The hub shipped exactly that bug, where discovery
     * and a manual `Event::listen` both fired the listener.
     */
    #[Test]
    public function it_is_registered_once_for_auth_event_occurred(): void
    {
        // Act + Assert

        $this->assertSame(1, count(Event::getListeners(AuthEventOccurred::class)));
    }

    /**
     * Seed the roles the User factory assigns.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /*
     * Audit Persistence Tests
     * -----------------------
     */

    /**
     * Persist the audit row when the event is dispatched.
     *
     * The queue connection is sync in tests, so the queued listener runs inline
     * and the row exists by the time the dispatch returns.
     */
    #[Test]
    public function it_persists_the_audit_row_for_a_dispatched_event(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        // Act

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::Login,
            userId: $user->id,
            email: $user->email,
            ipAddress: '127.0.0.1',
            userAgent: 'phpunit',
        ));

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $user->id,
            'event' => AuthAuditEvent::Login->value,
            'outcome' => null,
            'email' => $user->email,
        ]);
    }

    /**
     * Persist the outcome carried by the event on the audit row.
     *
     * A `Failed` outcome round-trips the queued listener untouched, so the
     * stored row answers whether the recorded activity worked.
     */
    #[Test]
    public function it_persists_the_outcome_carried_by_the_event(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        // Act

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::LoginFailed,
            outcome: AuditOutcome::Failed,
            userId: $user->id,
            email: $user->email,
            ipAddress: '127.0.0.1',
            userAgent: 'phpunit',
        ));

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $user->id,
            'event' => AuthAuditEvent::LoginFailed->value,
            'outcome' => AuditOutcome::Failed->value,
            'email' => $user->email,
        ]);
    }

    /**
     * Cap an oversized User-Agent when the queued listener persists the audit row.
     */
    #[Test]
    public function it_caps_an_oversized_user_agent_when_the_queued_listener_persists_the_audit_row(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        $oversizedUserAgent = str_repeat('A', 1025);

        // Act

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::Login,
            userId: $user->id,
            email: $user->email,
            ipAddress: '127.0.0.1',
            userAgent: $oversizedUserAgent,
        ));

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $user->id,
            'event' => AuthAuditEvent::Login->value,
            'user_agent' => str_repeat('A', 1024),
        ]);
    }

    /*
     * Listener Registration Tests
     * ---------------------------
     */

    /**
     * Run the audit write on the queue, off the request hot path.
     */
    #[Test]
    public function it_runs_on_the_queue(): void
    {
        // Act + Assert

        /*
         * The listener is queued, not synchronous.
         */

        $this->assertContains(ShouldQueue::class, class_implements(RecordAuthAuditLog::class));
    }

    /**
     * Wire the event to the listener.
     */
    #[Test]
    public function it_is_registered_for_the_auth_event(): void
    {
        // Act

        /** @var list<string> $listeners */
        $listeners = Event::getListeners(AuthEventOccurred::class);

        // Assert

        /*
         * Dispatch is routed to the listener (auto-discovery or explicit).
         */

        $this->assertNotEmpty($listeners);
    }

    /*
     * Location Enrichment Tests
     * -------------------------
     */

    /**
     * Persist the audit row when the geo lookup throws.
     *
     * The listener's own docblock claims lookups fail open, but nothing enforced
     * it: the bound `NullGeoIpLocator` happens to return null rather than throw, so
     * the claim held by accident. A real provider that raised - during an outage, or
     * on a hostile address - would abort the write before it happened, so the
     * authentication record would be retried and then lost. Location is enrichment;
     * the row is the evidence.
     */
    #[Test]
    public function it_persists_the_audit_row_when_the_geo_lookup_fails(): void
    {
        // Arrange

        $user = User::factory()->user()->create();

        $locator = new class implements GeoIpLocator
        {
            public function locate(?string $ipAddress): ?GeoIpLocation
            {
                throw new RuntimeException('Geo Provider Unreachable');
            }
        };

        $listener = new RecordAuthAuditLog(
            $this->app->make(RecordAuthAuditAction::class),
            $locator,
        );

        // Act

        $listener->handle(new AuthEventOccurred(
            data: new RecordAuthAuditData(
                event: AuthAuditEvent::Login,
                userId: $user->id,
                ipAddress: '203.0.113.10',
            ),
        ));

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::Login->value,
            'user_id' => $user->id,
            'location_city' => null,
            'location_country' => null,
        ]);
    }

    /**
     * Persist the location resolved from the event IP on the audit row.
     *
     * A capturing fake GeoIpLocator is bound in the container so the test
     * proves the queued listener enriches from `$event->data->ipAddress`
     * without depending on MaxMind availability.
     */
    #[Test]
    public function it_persists_the_location_resolved_from_the_event_ip(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        $locator = new class implements GeoIpLocator
        {
            /** @var list<string> */
            private array $locatedIps = [];

            /**
             * @return list<string> the IPs the locator received
             */
            public function locatedIps(): array
            {
                return $this->locatedIps;
            }

            public function locate(?string $ipAddress): ?GeoIpLocation
            {
                if ($ipAddress === null || $ipAddress === '') {
                    return null;
                }

                $this->locatedIps[] = $ipAddress;

                return new GeoIpLocation(city: 'Mountain View', country: 'US');
            }
        };

        $this->app->instance(GeoIpLocator::class, $locator);

        // Act

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::Login,
            userId: $user->id,
            email: $user->email,
            ipAddress: '8.8.8.8',
            userAgent: 'phpunit',
        ));

        // Assert

        $this->assertSame(['8.8.8.8'], $locator->locatedIps());

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $user->id,
            'event' => AuthAuditEvent::Login->value,
            'location_city' => 'Mountain View',
            'location_country' => 'US',
        ]);
    }

    /**
     * Persist null location fields when the real locator fails open.
     *
     * PHPUnit runs as `testing` with no MMDB present, so the private-range
     * skip applies before any Reader lookup.
     */
    #[Test]
    public function it_persists_null_location_fields_when_the_lookup_fails_open(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        // Act

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::Login,
            userId: $user->id,
            email: $user->email,
            ipAddress: '127.0.0.1',
            userAgent: 'phpunit',
        ));

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $user->id,
            'event' => AuthAuditEvent::Login->value,
            'location_city' => null,
            'location_country' => null,
        ]);
    }
}
