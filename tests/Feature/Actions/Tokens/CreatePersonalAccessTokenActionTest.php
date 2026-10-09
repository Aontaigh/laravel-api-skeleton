<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Tokens;

use App\Actions\Tokens\CreatePersonalAccessTokenAction;
use App\DataTransferObjects\Tokens\CreateTokenData;
use App\Exceptions\InvalidTokenAbilitiesException;
use App\Exceptions\InvalidTokenExpirationException;
use App\Models\User;
use App\Services\Permissions\PermissionAbilityCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for CreatePersonalAccessTokenAction against the database.
 */
#[CoversClass(CreatePersonalAccessTokenAction::class)]
#[CoversClass(PermissionAbilityCatalog::class)]
final class CreatePersonalAccessTokenActionTest extends TestCase
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
     * Seed the permission catalog the Action validates against.
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
     * Issue a Token with normalized abilities.
     */
    #[Test]
    public function it_issues_a_token_with_normalized_abilities(): void
    {
        // Arrange

        Carbon::setTestNow('2026-01-15 10:00:00');

        /** @var User $user */
        $user = User::factory()->user()->create();

        $data = new CreateTokenData(
            forUser: $user,
            name: 'CLI Token',
            abilities: ['tokens.list-own'],
        );

        // Act

        $issued = app(CreatePersonalAccessTokenAction::class)->execute($data);

        // Assert

        $this->assertSame('CLI Token', $issued->accessToken->name);
        $this->assertSame(['tokens.list-own'], $issued->accessToken->abilities);
        $this->assertNotNull($issued->accessToken->expires_at);
        $this->assertSame(
            '2026-04-15 10:00:00',
            $issued->accessToken->expires_at->toDateTimeString(),
        );
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'CLI Token',
            'tokenable_id' => $user->id,
        ]);

        Carbon::setTestNow();
    }

    /**
     * Refuse a Token that is asked to never expire.
     *
     * The machine credential contract is that a credential always ages out, and
     * a caller-chosen `null` expiry is the one way to opt out, so the Action
     * refuses it rather than persisting an immortal token.
     */
    #[Test]
    public function it_refuses_a_token_asked_to_never_expire(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        $data = new CreateTokenData(
            forUser: $user,
            name: 'Never Expiring Token',
            abilities: ['tokens.list-own'],
            expiresAt: null,
            useConfiguredExpiration: false,
        );

        // Act

        $exception = null;

        try {
            app(CreatePersonalAccessTokenAction::class)->execute($data);
        } catch (InvalidTokenExpirationException $caught) {
            $exception = $caught;
        }

        // Assert

        $this->assertInstanceOf(InvalidTokenExpirationException::class, $exception);
        $this->assertSame('Tokens Must Expire', $exception->getMessage());
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'Never Expiring Token']);
    }

    /**
     * Refuse a Token expiry beyond the configured maximum lifetime.
     */
    #[Test]
    public function it_refuses_a_token_expiry_beyond_the_maximum_lifetime(): void
    {
        // Arrange

        config(['api.token_max_expiration_days' => 30]);

        Carbon::setTestNow('2026-01-15 10:00:00');

        /** @var User $user */
        $user = User::factory()->user()->create();

        $data = new CreateTokenData(
            forUser: $user,
            name: 'Overlong Token',
            abilities: ['tokens.list-own'],
            expiresAt: Carbon::parse('2026-03-01 00:00:00'),
            useConfiguredExpiration: false,
        );

        // Act

        $exception = null;

        try {
            app(CreatePersonalAccessTokenAction::class)->execute($data);
        } catch (InvalidTokenExpirationException $caught) {
            $exception = $caught;
        }

        // Assert

        $this->assertInstanceOf(InvalidTokenExpirationException::class, $exception);
        $this->assertSame('Token Expiry Exceeds The Maximum Lifetime', $exception->getMessage());
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'Overlong Token']);

        Carbon::setTestNow();
    }

    /**
     * Fall back to the ceiling when the configured lifetime would never expire.
     */
    #[Test]
    public function it_falls_back_to_the_maximum_lifetime_when_the_configured_lifetime_is_zero(): void
    {
        // Arrange

        Carbon::setTestNow('2026-01-15 10:00:00');

        config([
            'api.token_expiration_days' => 0,
            'api.token_max_expiration_days' => 366,
        ]);

        /** @var User $user */
        $user = User::factory()->user()->create();

        $data = new CreateTokenData(
            forUser: $user,
            name: 'Bounded Token',
            abilities: ['tokens.list-own'],
        );

        // Act

        $issued = app(CreatePersonalAccessTokenAction::class)->execute($data);

        // Assert

        $this->assertSame(
            '2027-01-16 10:00:00',
            $issued->accessToken->expires_at?->toDateTimeString(),
        );

        Carbon::setTestNow();
    }

    /**
     * Reject unknown abilities before persisting.
     */
    #[Test]
    public function it_rejects_unknown_abilities_before_persisting(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        $data = new CreateTokenData(
            forUser: $user,
            name: 'Bad Token',
            abilities: ['read'],
        );

        // Act + Assert

        $this->expectException(InvalidTokenAbilitiesException::class);

        try {
            app(CreatePersonalAccessTokenAction::class)->execute($data);
        } finally {
            $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'Bad Token']);
        }
    }
}
