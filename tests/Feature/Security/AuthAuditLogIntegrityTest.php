<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Actions\Auth\RecordAuthAuditAction;
use App\Contracts\GeoIp\GeoIpLocator;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\DataTransferObjects\GeoIp\GeoIpLocation;
use App\Enums\AuditOutcome;
use App\Enums\AuthAuditEvent;
use App\Enums\ClientIneligibilityReason;
use App\Events\AuthEventOccurred;
use App\Listeners\RecordAuthAuditLog;
use App\Models\AuthAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Adversarial tests for the integrity of the authentication audit trail.
 *
 * `auth_audit_logs` is the evidence base an incident responder works from, and its
 * value rests on properties no single behavioural test can establish: that a record
 * is written exactly once, that it survives a failing dependency, that it captures
 * the moment of the event rather than the moment of the write, and that it cannot be
 * written or edited by anyone but the application itself.
 *
 * Each test below is deliberately written so that it fails on a *silent* regression.
 * A duplicated row still exists, still carries the right values, and still satisfies
 * every "was this attempt recorded?" assertion - what breaks is arithmetic. A lost
 * row leaves the rest of the suite green. These are the tests that catch both.
 */
#[CoversNothing]
class AuthAuditLogIntegrityTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Record exactly one row for a single dispatched event.
     *
     * A listener that is both auto-discovered from `app/Listeners` and registered by
     * hand runs twice per dispatch, and every authentication event then writes two
     * identical rows. Nothing else catches it: the row still exists, still carries the
     * right values, and every "was this attempt recorded?" assertion passes. What
     * breaks is arithmetic - failed-attempt counts per IP double, the table grows at
     * twice the rate, and a duplicate is indistinguishable from a genuine second
     * attempt during an investigation.
     */
    #[Test]
    public function it_records_exactly_one_row_for_a_single_dispatched_event(): void
    {
        // Arrange

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::Login,
            email: 'subject@example.com',
            ipAddress: '203.0.113.10',
        ));

        // Act

        // Scoped to the event just dispatched. The listener is queued, so a row written
        // by an earlier test's job can land inside this test's transaction; counting the
        // whole table would report that leak as a duplicate registration.
        $rows = AuthAuditLog::query()
            ->where('event', AuthAuditEvent::Login->value)
            ->get();

        // Assert

        self::assertCount(
            1,
            $rows,
            'One dispatched event produced '.$rows->count().' rows for '.AuthAuditEvent::Login->value
            .' - the audit listener is registered more than once',
        );
    }

    /**
     * Keep the record when an optional enrichment dependency fails.
     *
     * Location is enrichment, not evidence. A geo lookup that throws must not cost us
     * the authentication record itself, so the failure is contained and the row is
     * written without a country. The bound locator in this suite throws rather than
     * returning null, which is the harsher of the two failure modes.
     */
    #[Test]
    public function it_persists_the_audit_row_when_the_geo_lookup_throws(): void
    {
        // Arrange

        $this->swap(GeoIpLocator::class, new class implements GeoIpLocator
        {
            public function locate(?string $ipAddress): ?GeoIpLocation
            {
                throw new \RuntimeException('Geo lookup unavailable');
            }
        });

        // Act

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::Login,
            email: 'subject@example.com',
            ipAddress: '203.0.113.10',
        ));

        // Assert

        self::assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::Login->value,
            'email' => 'subject@example.com',
            'ip_address' => '203.0.113.10',
            'location_country' => null,
        ]);
    }

    /**
     * Record the moment of the event, not the moment of the write.
     *
     * The listener is queued, so it runs outside the request that produced the event,
     * and by then the ambient request has moved on to the next one in the worker. If
     * the listener reached back for the current request instead of using the captured
     * address, every queued row would be attributed to whichever request happened to
     * be in flight, and the column would be worthless for incident response.
     */
    #[Test]
    public function it_records_the_address_captured_at_dispatch_not_the_ambient_request(): void
    {
        // Arrange

        // The worker is shared, so an ambient request exists when the queued listener
        // finally runs. Its address must not leak into the row.
        request()->server->set('REMOTE_ADDR', '198.51.100.99');

        $data = new RecordAuthAuditData(
            event: AuthAuditEvent::Login,
            email: 'subject@example.com',
            ipAddress: '203.0.113.10',
        );

        // Act

        AuthEventOccurred::dispatch($data);

        // Assert

        self::assertDatabaseHas('auth_audit_logs', [
            'email' => 'subject@example.com',
            'ip_address' => '203.0.113.10',
        ]);

        self::assertDatabaseMissing('auth_audit_logs', [
            'ip_address' => '198.51.100.99',
        ]);
    }

    /**
     * Keep the retry policy from being weakened without a test noticing.
     *
     * The row is written off the hot path, so the queue's retry budget is the only
     * thing standing between a transient database blip and a lost audit record.
     * Lowering `tries` to 1 turns any blip into silent evidence loss, and it would
     * not fail any behavioural test - hence this assertion on the configuration itself.
     */
    #[Test]
    public function it_holds_a_retry_budget_that_can_absorb_a_transient_failure(): void
    {
        // Arrange

        $listener = $this->app->make(RecordAuthAuditLog::class);

        // Act

        // Assert

        self::assertGreaterThan(
            1,
            $listener->tries,
            'A single attempt loses records on any transient failure',
        );
        self::assertGreaterThan(0, $listener->timeout);
    }

    /**
     * Expose no HTTP route that writes the audit trail.
     *
     * There is no legitimate reason for an API client to create, edit, or delete
     * authentication evidence. If a route ever appears that resolves to a controller
     * which writes this table, it is either a bug or an unauthenticated forgery
     * primitive.
     */
    #[Test]
    public function it_exposes_no_route_that_writes_the_audit_trail(): void
    {
        // Arrange

        $writers = [
            RecordAuthAuditAction::class,
            RecordAuthAuditLog::class,
            AuthAuditLog::class,
        ];

        // Act

        /** @var list<string> $targets */
        $targets = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $action = $route->getActionName();

            if ($action !== '') {
                $targets[] = $action;
            }
        }

        // Assert

        foreach ($writers as $writer) {
            foreach ($targets as $target) {
                self::assertStringNotContainsString(
                    $writer,
                    $target,
                    "A route resolves to the audit writer {$writer}: {$target}",
                );
            }
        }
    }

    /**
     * Bound every persisted value that came verbatim from the request.
     *
     * The audit table is read by support staff and exported to analytics, so no
     * client-influenced value may be stored at whatever length it arrived. `ip_address`
     * and `email` are bounded by their column types, but `user_agent` is `text` and is
     * bounded only in code - so a cap that is later removed from the writer would widen
     * a staff-facing table with no schema change to warn anyone. This asserts the
     * *persisted* length rather than the constant, so it fails either way the bound
     * disappears.
     *
     * @param string $column
     */
    #[Test]
    #[DataProvider('oversizedClientInfluencedValuesProvider')]
    public function it_bounds_every_persisted_client_influenced_value(string $column, int $expectedMaximum): void
    {
        // Arrange

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::Login,
            email: 'subject@example.com',
            ipAddress: '203.0.113.10',
            userAgent: str_repeat('A', 100_000),
        ));

        // Act

        $stored = AuthAuditLog::query()->latest('id')->value($column);

        self::assertIsString($stored);

        // Assert

        self::assertLessThanOrEqual(
            $expectedMaximum,
            mb_strlen($stored),
            "auth_audit_logs.{$column} stored ".mb_strlen($stored).' characters, above its '.$expectedMaximum.' bound',
        );
    }

    /**
     * Attribute a client-credential refusal to the reason it was refused.
     *
     * The application verifies a machine credential and then declines it for a policy
     * reason. Recording that as a plain failure - the same outcome a typo produces -
     * means an incident responder cannot separate a deliberate policy decline from a
     * credential attack, which is the whole question they are trying to answer.
     */
    #[Test]
    public function it_attributes_a_client_credential_refusal_to_its_reason(): void
    {
        // Arrange

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::ClientTokenExchangeFailed,
            outcome: AuditOutcome::Refused,
            clientIneligibilityReason: ClientIneligibilityReason::SuspendedOwner,
            ipAddress: '203.0.113.10',
        ));

        // Act

        // Assert

        self::assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::ClientTokenExchangeFailed->value,
            'outcome' => AuditOutcome::Refused->value,
            'client_ineligibility_reason' => ClientIneligibilityReason::SuspendedOwner->value,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */
    /**
     * Client-influenced columns paired with the maximum they may ever store.
     *
     * @return array<string, array{string, int}>
     */
    public static function oversizedClientInfluencedValuesProvider(): array
    {
        return [
            'user agent' => ['user_agent', 1024],
            'ip address' => ['ip_address', 45],
        ];
    }
}
