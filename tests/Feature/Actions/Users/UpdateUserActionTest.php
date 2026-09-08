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
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Seed roles so factory role states can assign them.
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
}
