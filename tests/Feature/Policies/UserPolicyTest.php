<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\Team;
use App\Models\User;
use App\Policies\UserPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for UserPolicy edge cases that are easy to miss in controller tests.
 */
#[CoversClass(UserPolicy::class)]
final class UserPolicyTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @var UserPolicy the policy under test */
    private UserPolicy $policy;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Seed permissions and instantiate the policy.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->policy = new UserPolicy;
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Deny a User from deleting their own account.
     */
    #[Test]
    public function it_denies_a_user_from_deleting_their_own_account(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();

        // Act

        $allowed = $this->policy->delete($manager, $manager);

        // Assert

        $this->assertFalse($allowed);
    }

    /**
     * Deny Team reassignment to non-admins.
     */
    #[Test]
    public function it_denies_team_reassignment_to_non_admins(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();

        // Act

        $allowed = $this->policy->reassignTeam($manager);

        // Assert

        $this->assertFalse($allowed);
    }

    /**
     * Deny a manager from viewing a User on another Team.
     */
    #[Test]
    public function it_denies_a_manager_from_viewing_a_user_on_another_team(): void
    {
        // Arrange

        $homeTeam = Team::factory()->create();
        $otherTeam = Team::factory()->create();

        /** @var User $manager */
        $manager = User::factory()->for($homeTeam)->manager()->create();

        /** @var User $outsider */
        $outsider = User::factory()->for($otherTeam)->user()->create();

        // Act

        $allowed = $this->policy->view($manager, $outsider);

        // Assert

        $this->assertFalse($allowed);
    }

    /**
     * Allow an admin to view a User on any Team.
     */
    #[Test]
    public function it_allows_an_admin_to_view_a_user_on_any_team(): void
    {
        // Arrange

        $otherTeam = Team::factory()->create();

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $outsider */
        $outsider = User::factory()->for($otherTeam)->user()->create();

        // Act

        $allowed = $this->policy->view($admin, $outsider);

        // Assert

        $this->assertTrue($allowed);
    }

    /**
     * Allow interactive Users to view their own profile via `GET /me`.
     */
    #[Test]
    public function it_allows_interactive_users_to_view_their_own_profile(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();

        // Act

        $allowed = $this->policy->viewMe($user);

        // Assert

        $this->assertTrue($allowed);
    }

    /**
     * Deny service accounts from the profile endpoint.
     */
    #[Test]
    public function it_denies_service_accounts_from_the_profile_endpoint(): void
    {
        // Arrange

        /** @var User $serviceUser */
        $serviceUser = User::factory()->serviceAccount()->service()->create();

        // Act

        $allowed = $this->policy->viewMe($serviceUser);

        // Assert

        $this->assertFalse($allowed);
    }
}
