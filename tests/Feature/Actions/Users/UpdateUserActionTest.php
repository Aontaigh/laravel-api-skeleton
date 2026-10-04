<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Users;

use App\Actions\Users\UpdateUserAction;
use App\DataTransferObjects\Users\UpdateUserData;
use App\Enums\RoleName;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for UpdateUserAction against the database.
 */
#[CoversClass(UpdateUserAction::class)]
final class UpdateUserActionTest extends TestCase
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
     * Seed roles so factory role states can assign them.
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
     * Update the requested attributes.
     */
    #[Test]
    public function it_updates_the_requested_attributes(): void
    {
        // Arrange

        /** @var Team $team */
        $team = Team::factory()->create();

        /** @var Team $otherTeam */
        $otherTeam = Team::factory()->create();

        /** @var User $user */
        $user = User::factory()->for($team)->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        // Act

        $updated = app(UpdateUserAction::class)->execute(new UpdateUserData(
            user: $user,
            name: 'Updated Name',
            teamId: $otherTeam->id,
        ));

        // Assert

        $this->assertSame('Updated Name', $updated->name);
        $this->assertSame('original@example.com', $updated->email);
        $this->assertSame($otherTeam->id, $updated->team_id);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'original@example.com',
            'team_id' => $otherTeam->id,
        ]);
    }

    /**
     * Sync the role when one is provided.
     */
    #[Test]
    public function it_syncs_the_role_when_one_is_provided(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        // Act

        $updated = app(UpdateUserAction::class)->execute(new UpdateUserData(
            user: $user,
            role: RoleName::Manager,
        ));

        // Assert

        $this->assertTrue($updated->hasRole(RoleName::Manager));
        $this->assertFalse($updated->hasRole(RoleName::User));
    }

    /**
     * Refuse to demote the last remaining Admin.
     */
    #[Test]
    public function it_refuses_to_demote_the_last_admin(): void
    {
        // Arrange

        /** @var User $lastAdmin */
        $lastAdmin = User::factory()->admin()->create();

        // Act

        try {
            app(UpdateUserAction::class)->execute(new UpdateUserData(
                user: $lastAdmin,
                role: RoleName::Manager,
            ));

            $this->fail('Demoting the last Admin did not throw');
        } catch (ValidationException $exception) {
            // Assert

            $this->assertSame(
                ['role' => ['Cannot Demote The Last Admin']],
                $exception->errors(),
            );
            $this->assertTrue($lastAdmin->refresh()->hasRole(RoleName::Admin));
        }
    }

    /**
     * Demote an Admin while another Admin remains.
     */
    #[Test]
    public function it_demotes_an_admin_while_another_remains(): void
    {
        // Arrange

        User::factory()->admin()->create();

        /** @var User $demoted */
        $demoted = User::factory()->admin()->create();

        // Act

        $updated = app(UpdateUserAction::class)->execute(new UpdateUserData(
            user: $demoted,
            role: RoleName::Manager,
        ));

        // Assert

        $this->assertTrue($updated->hasRole(RoleName::Manager));
    }

    /**
     * Revoke the outstanding reset token when the role changes.
     *
     * A link requested while the account was an interactive user must not
     * stay consumable after the role - and so the account's standing -
     * changes.
     */
    /**
     * Revoke the reset token when a round-trip narrows a multi-role user.
     *
     * `hasRole` answers membership, not set equality, so a user holding both
     * `user` and `manager` who re-sends `manager` passes the guard while
     * `syncRoles` still drops `user`. That is a real reduction in privilege, so the
     * pending reset link must not survive it. The single-role round-trip case stays
     * a no-op, pinned by `it_keeps_an_outstanding_reset_token_when_the_role_is_unchanged`.
     */
    #[Test]
    public function it_revokes_an_outstanding_reset_token_when_a_round_trip_narrows_a_multi_role_user(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();
        $user->assignRole(RoleName::Manager);

        Password::createToken($user);

        $this->assertTrue($user->hasRole(RoleName::User));
        $this->assertTrue($user->hasRole(RoleName::Manager));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);

        // Act

        app(UpdateUserAction::class)->execute(new UpdateUserData(
            user: $user,
            role: RoleName::Manager,
        ));

        // Assert

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertTrue($user->refresh()->hasRole(RoleName::Manager));
        $this->assertFalse($user->hasRole(RoleName::User));
    }

    /**
     * Delete the outstanding reset token when the User receives a new Role.
     */
    #[Test]
    public function it_revokes_the_outstanding_reset_token_when_the_role_changes(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        Password::createToken($user);

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);

        // Act

        app(UpdateUserAction::class)->execute(new UpdateUserData(
            user: $user,
            role: RoleName::Manager,
        ));

        // Assert

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    /**
     * Keep the outstanding reset token on a name-only update.
     *
     * The token is revoked for privilege changes only: renaming an account
     * does not change what can sign in, so a pending link the user may
     * still need survives.
     */
    #[Test]
    public function it_keeps_the_outstanding_reset_token_on_a_name_only_update(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        Password::createToken($user);

        // Act

        app(UpdateUserAction::class)->execute(new UpdateUserData(
            user: $user,
            name: 'Renamed Account',
        ));

        // Assert

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    /**
     * Keep the outstanding reset token when the submitted role is unchanged.
     *
     * A direct API consumer is not required to omit a field it is leaving
     * alone, so re-sending the account's existing role performs no privilege
     * change. Revoking on that no-op would silently break a pending password
     * reset for a caller that round-trips the whole record.
     */
    #[Test]
    public function it_keeps_an_outstanding_reset_token_when_the_role_is_unchanged(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        Password::createToken($user);

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);

        // Act

        app(UpdateUserAction::class)->execute(new UpdateUserData(
            user: $user,
            role: RoleName::User,
        ));

        // Assert

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }
}
