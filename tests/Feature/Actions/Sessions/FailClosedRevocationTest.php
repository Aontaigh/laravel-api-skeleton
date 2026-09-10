<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Sessions;

use App\Actions\Sessions\InvalidateStoredSessionAction;
use App\Actions\Sessions\RevokeAllWebSessionsForUserAction;
use App\Actions\Sessions\RevokeWebSessionAction;
use App\Actions\Users\UpdatePasswordAction;
use App\DataTransferObjects\Users\UpdatePasswordData;
use App\Models\User;
use App\Models\WebSession;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the fail-closed fallback when a session store refuses a
 * destroy: the revocation Actions must recall the owner's cookies via a
 * `session_version` bump instead of silently leaving a live payload behind.
 */
#[CoversClass(InvalidateStoredSessionAction::class)]
#[CoversClass(UpdatePasswordAction::class)]
#[CoversClass(RevokeAllWebSessionsForUserAction::class)]
#[CoversClass(RevokeWebSessionAction::class)]
final class FailClosedRevocationTest extends TestCase
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
     * Force every session-store destroy to report failure.
     *
     * Partial-proxies the real SessionManager so unrelated facade calls
     * (`driver()`, `isStarted()`) still pass through to the live instance.
     *
     * @return void
     */
    private function failTheSessionStore(): void
    {
        $manager = Mockery::mock($this->app->make('session'))->makePartial();
        $manager->shouldReceive('getHandler')->andReturn(
            Mockery::mock()->shouldReceive('destroy')->andReturn(false)->getMock(),
        );
        Session::swap($manager);
    }

    /**
     * Force every session-store destroy to report success.
     *
     * Same partial proxy shape as {@see failTheSessionStore()}.
     *
     * @return void
     */
    private function succeedTheSessionStore(): void
    {
        $manager = Mockery::mock($this->app->make('session'))->makePartial();
        $manager->shouldReceive('getHandler')->andReturn(
            Mockery::mock()->shouldReceive('destroy')->andReturn(true)->getMock(),
        );
        Session::swap($manager);
    }

    /**
     * Assert the persisted session_version against an expected value.
     *
     * @param  User $user     the User whose version is under test
     * @param  int  $expected the expected stamped version
     * @return void
     */
    private function assertSessionVersion(User $user, int $expected): void
    {
        $fresh = $user->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame($expected, $fresh->session_version);
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

    /**
     * Recall every cookie when a password-change payload destroy fails.
     */
    #[Test]
    public function it_fails_closed_when_a_password_change_destroy_fails(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create(['password' => Hash::make('OldPassword#1')]);

        WebSession::factory()->count(2)->for($user)->create();

        /** @var int $versionBefore */
        $versionBefore = $user->session_version;

        $this->failTheSessionStore();

        $action = $this->app->make(UpdatePasswordAction::class);

        // Act

        $action->execute($user, new UpdatePasswordData(
            currentPassword: 'OldPassword#1',
            newPassword: 'Xq7#mK2$vL9pTzW4',
        ), null);

        // Assert

        /*
         * The happy-path rotation plus the fail-closed fallback: two bumps.
         */
        $this->assertSessionVersion($user, $versionBefore + 2);
    }

    /**
     * Recall every cookie when a logout payload destroy fails.
     */
    #[Test]
    public function it_fails_closed_when_a_logout_destroy_fails(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        WebSession::factory()->count(2)->for($user)->create();

        /** @var int $versionBefore */
        $versionBefore = $user->session_version;

        $this->failTheSessionStore();

        $action = $this->app->make(RevokeAllWebSessionsForUserAction::class);

        // Act

        $action->execute($user);

        // Assert

        /*
         * Revoke-all performs no happy-path rotation: the fallback is the
         * only bump.
         */
        $this->assertSessionVersion($user, $versionBefore + 1);
    }

    /**
     * Recall every cookie when a surgical revoke's payload destroy fails.
     */
    #[Test]
    public function it_fails_closed_when_a_surgical_revoke_destroy_fails(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        $webSession = WebSession::factory()->for($user)->create();

        /** @var int $versionBefore */
        $versionBefore = $user->session_version;

        $this->failTheSessionStore();

        $action = $this->app->make(RevokeWebSessionAction::class);

        // Act

        $action->execute($webSession, null);

        // Assert

        $this->assertSessionVersion($user, $versionBefore + 1);
    }

    /**
     * Never invoke the fallback when every payload destroyed successfully.
     */
    #[Test]
    public function it_does_not_fail_closed_when_all_destroys_succeed(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create(['password' => Hash::make('OldPassword#1')]);

        WebSession::factory()->count(2)->for($user)->create();

        /** @var int $versionBefore */
        $versionBefore = $user->session_version;

        $this->succeedTheSessionStore();

        $action = $this->app->make(UpdatePasswordAction::class);

        // Act

        $action->execute($user, new UpdatePasswordData(
            currentPassword: 'OldPassword#1',
            newPassword: 'Xq7#mK2$vL9pTzW4',
        ), null);

        // Assert

        /*
         * Only the happy-path rotation: no extra fail-closed bump.
         */
        $this->assertSessionVersion($user, $versionBefore + 1);
    }
}
