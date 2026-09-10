<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Sessions;

use App\Actions\Sessions\RegisterWebSessionAction;
use App\Contracts\GeoIp\GeoIpLocator;
use App\DataTransferObjects\GeoIp\GeoIpLocation;
use App\DataTransferObjects\Sessions\RegisterWebSessionData;
use App\Models\User;
use App\Models\WebSession;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the device label derivation in the web session registry.
 */
#[CoversClass(RegisterWebSessionAction::class)]
final class RegisterWebSessionActionTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

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

    /**
     * Keep an explicit device label exactly as supplied.
     */
    #[Test]
    public function it_keeps_an_explicit_device_label(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        // Act

        $webSession = app(RegisterWebSessionAction::class)->execute(new RegisterWebSessionData(
            user: $user,
            sessionId: 'session-explicit',
            deviceName: 'Work Laptop',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            rememberMe: false,
        ));

        // Assert

        $this->assertSame('Work Laptop', $webSession->device_name);
    }

    /**
     * Derive the device label from the user agent when none is supplied.
     */
    #[Test]
    public function it_derives_a_device_label_from_the_user_agent_when_blank(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        $agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

        // Act

        $webSession = app(RegisterWebSessionAction::class)->execute(new RegisterWebSessionData(
            user: $user,
            sessionId: 'session-derived',
            deviceName: '',
            ipAddress: '127.0.0.1',
            userAgent: $agent,
            rememberMe: false,
        ));

        // Assert

        $this->assertSame('macOS · Chrome', $webSession->device_name);
    }

    /**
     * Persist the row in the registry.
     */
    #[Test]
    public function it_persists_the_registry_row(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        // Act

        app(RegisterWebSessionAction::class)->execute(new RegisterWebSessionData(
            user: $user,
            sessionId: 'session-persisted',
            deviceName: 'Work Laptop',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            rememberMe: false,
        ));

        // Assert

        $this->assertDatabaseHas(WebSession::class, [
            'user_id' => $user->id,
            'session_id' => 'session-persisted',
            'device_name' => 'Work Laptop',
        ]);
    }

    /**
     * Persist the resolved location on the registry row.
     *
     * A fake GeoIpLocator is bound in the container so the test pins the
     * enrichment wiring without depending on MaxMind availability.
     */
    #[Test]
    public function it_persists_the_resolved_location_on_the_registry_row(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        $this->app->instance(GeoIpLocator::class, new class implements GeoIpLocator
        {
            public function locate(?string $ipAddress): ?GeoIpLocation
            {
                return $ipAddress === null
                    ? null
                    : new GeoIpLocation(city: 'Mountain View', country: 'US');
            }
        });

        // Act

        app(RegisterWebSessionAction::class)->execute(new RegisterWebSessionData(
            user: $user,
            sessionId: 'session-located',
            deviceName: 'Work Laptop',
            ipAddress: '8.8.8.8',
            userAgent: 'Mozilla/5.0',
            rememberMe: false,
        ));

        // Assert

        $this->assertDatabaseHas(WebSession::class, [
            'session_id' => 'session-located',
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
    public function it_persists_null_location_fields_when_the_locator_fails_open(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        // Act

        app(RegisterWebSessionAction::class)->execute(new RegisterWebSessionData(
            user: $user,
            sessionId: 'session-unlocated',
            deviceName: 'Work Laptop',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            rememberMe: false,
        ));

        // Assert

        $this->assertDatabaseHas(WebSession::class, [
            'session_id' => 'session-unlocated',
            'location_city' => null,
            'location_country' => null,
        ]);
    }
}
