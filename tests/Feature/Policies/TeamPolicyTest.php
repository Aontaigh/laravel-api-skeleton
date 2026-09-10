<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\Team;
use App\Models\User;
use App\Policies\TeamPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the Team policy.
 */
#[CoversClass(TeamPolicy::class)]
final class TeamPolicyTest extends TestCase
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
     * Seed permissions for the Spatie role gate.
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
     * Allow admins and managers to list and view Teams.
     */
    #[Test]
    public function it_allows_admins_and_managers_to_list_and_view_teams(): void
    {
        /* Arrange */

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var User $manager */
        $manager = User::factory()->manager()->create();
        /** @var Team $team */
        $team = Team::factory()->create();

        /* Act & Assert */

        $this->actingAs($admin)->getJson('/api/teams')->assertOk();
        $this->actingAs($admin)->getJson("/api/teams/{$team->id}")->assertOk();

        $this->actingAs($manager)->getJson('/api/teams')->assertOk();
        $this->actingAs($manager)->getJson("/api/teams/{$team->id}")->assertOk();
    }

    /**
     * Deny regular users without the `teams.list` permission.
     */
    #[Test]
    public function it_denies_regular_users(): void
    {
        /* Arrange */

        /** @var User $user */
        $user = User::factory()->user()->create();
        /** @var Team $team */
        $team = Team::factory()->create();

        /* Act & Assert */

        $this->actingAs($user)->getJson('/api/teams')->assertForbidden();
        $this->actingAs($user)->getJson("/api/teams/{$team->id}")->assertForbidden();
    }

    /**
     * Deny service accounts.
     */
    #[Test]
    public function it_denies_service_accounts(): void
    {
        /* Arrange */

        /** @var User $serviceUser */
        $serviceUser = User::factory()->serviceAccount()->service()->create();
        /** @var Team $team */
        $team = Team::factory()->create();

        /* Act & Assert */

        $this->actingAs($serviceUser)->getJson('/api/teams')->assertForbidden();
        $this->actingAs($serviceUser)->getJson("/api/teams/{$team->id}")->assertForbidden();
    }

    /**
     * Allow admins to create, update, and delete Teams.
     */
    #[Test]
    public function it_allows_admins_to_write_teams(): void
    {
        /* Arrange */

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['name' => 'Engineering']);

        /* Act & Assert */

        $this->actingAs($admin)->postJson('/api/teams', ['name' => 'Platform'])->assertCreated();
        $this->actingAs($admin)->patchJson("/api/teams/{$team->id}", ['name' => 'Engineering Two'])->assertOk();

        /** @var Team $emptyTeam */
        $emptyTeam = Team::factory()->create(['name' => 'Empty']);
        $this->actingAs($admin)->deleteJson("/api/teams/{$emptyTeam->id}")->assertOk();
    }

    /**
     * Deny Managers the write endpoints: they hold `teams.list` only.
     */
    #[Test]
    public function it_denies_managers_the_write_endpoints(): void
    {
        /* Arrange */

        /** @var User $manager */
        $manager = User::factory()->manager()->create();
        /** @var Team $team */
        $team = Team::factory()->create(['name' => 'Engineering']);

        /* Act & Assert */

        $this->actingAs($manager)->postJson('/api/teams', ['name' => 'Platform'])->assertForbidden();
        $this->actingAs($manager)->patchJson("/api/teams/{$team->id}", ['name' => 'Platform'])->assertForbidden();
        $this->actingAs($manager)->deleteJson("/api/teams/{$team->id}")->assertForbidden();
    }
}
