<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\AuthenticateUserAction;
use App\DataTransferObjects\Auth\LoginCredentialsData;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for transparent password-hash upgrade on login (argon2id).
 */
#[CoversClass(AuthenticateUserAction::class)]
final class RehashOnLoginTest extends TestCase
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
     * Upgrade an outdated argon2id hash to the configured work factors on login.
     *
     * Pre-rehash the hash carries older, cheaper factors; afterwards it is a
     * current-factor argon2id hash that still verifies the original password,
     * proving the upgrade is transparent.
     */
    #[Test]
    public function it_upgrades_a_stale_argon2id_hash_to_current_factors_on_login(): void
    {
        // Arrange

        $password = 'LegacySecret99';

        /** @var User $user */
        $user = User::factory()->user()->create();

        /*
         * Write an argon2id hash with older, cheaper factors directly, bypassing
         * the hashed cast so the fixture mirrors a pre-bump deployment.
         */

        $staleHash = password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 2048, 'time_cost' => 1, 'threads' => 1]);

        $user->forceFill(['password' => $staleHash])->saveQuietly();

        $this->assertSame('argon2id', Hash::info($staleHash)['algoName']);
        $this->assertTrue(Hash::needsRehash($staleHash));

        // Act

        $authenticated = app(AuthenticateUserAction::class)->execute(new LoginCredentialsData(
            email: $user->email,
            password: $password,
        ));

        // Assert

        /** @var string $newHash */
        $newHash = $authenticated->refresh()->getRawOriginal('password');

        $this->assertSame('argon2id', Hash::info($newHash)['algoName']);
        $this->assertTrue(Hash::check($password, $newHash));
        $this->assertFalse(Hash::needsRehash($newHash));
    }

    /**
     * Refuse a suspended User before any rehash, leaving the stored hash untouched.
     *
     * The suspension guard runs ahead of rehash-on-login so a stale-hash User who
     * is also suspended can never log in to have their hash upgraded: guarding the
     * write first is what makes the timing side-channel safe.
     */
    #[Test]
    public function it_does_not_rehash_a_suspended_user_even_with_a_stale_hash(): void
    {
        // Arrange

        $password = 'LegacySecret99';

        /** @var User $user */
        $user = User::factory()->user()->suspended()->create();

        $staleHash = password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 2048, 'time_cost' => 1, 'threads' => 1]);

        $user->forceFill(['password' => $staleHash])->saveQuietly();

        // Act + Assert

        try {
            app(AuthenticateUserAction::class)->execute(new LoginCredentialsData(
                email: $user->email,
                password: $password,
            ));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException) {
            /** @var string $unchanged */
            $unchanged = $user->refresh()->getRawOriginal('password');

            $this->assertSame($staleHash, $unchanged);
        }
    }

    /**
     * Leave a current argon2id hash untouched on login.
     */
    #[Test]
    public function it_leaves_a_current_argon2id_hash_unchanged_on_login(): void
    {
        // Arrange

        $password = 'CurrentSecret99';

        /** @var User $user */
        $user = User::factory()->user()->create([
            'password' => Hash::make($password),
        ]);

        /** @var string $originalHash */
        $originalHash = $user->getRawOriginal('password');

        $this->assertSame('argon2id', Hash::info($originalHash)['algoName']);

        // Act

        $authenticated = app(AuthenticateUserAction::class)->execute(new LoginCredentialsData(
            email: $user->email,
            password: $password,
        ));

        // Assert

        $this->assertSame($originalHash, $authenticated->refresh()->getRawOriginal('password'));
    }
}
