<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\User;
use App\Policies\PermissionPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for PermissionPolicy.
 */
#[CoversClass(PermissionPolicy::class)]
final class PermissionPolicyTest extends TestCase
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

    private PermissionPolicy $policy;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Seed permissions and construct the policy under test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->policy = new PermissionPolicy;
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Allow interactive roles that create tokens to list permissions.
     */
    #[Test]
    public function it_allows_token_creators_to_list_permissions(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        /** @var User $manager */
        $manager = User::factory()->manager()->create();
        /** @var User $user */
        $user = User::factory()->user()->create();

        // Act + Assert

        $this->assertTrue($this->policy->viewAny($admin));
        $this->assertTrue($this->policy->viewAny($manager));
        $this->assertTrue($this->policy->viewAny($user));
    }

    /**
     * Deny service accounts without permissions list access.
     */
    #[Test]
    public function it_denies_service_accounts_without_permissions_list_access(): void
    {
        // Arrange

        /** @var User $service */
        $service = User::factory()->service()->create();

        // Act + Assert

        $this->assertFalse($this->policy->viewAny($service));
    }
}
