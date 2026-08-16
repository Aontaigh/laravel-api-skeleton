<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\ApiClient;
use App\Models\User;
use App\Policies\ApiClientPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for ApiClientPolicy.
 */
#[CoversClass(ApiClientPolicy::class)]
final class ApiClientPolicyTest extends TestCase
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

    private ApiClientPolicy $policy;

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
        $this->policy = new ApiClientPolicy;
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Allow admins to manage API clients.
     */
    #[Test]
    public function it_allows_admins_to_manage_api_clients(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $client = ApiClient::factory()->create();

        // Act + Assert

        $this->assertTrue($this->policy->viewAny($admin));
        $this->assertTrue($this->policy->view($admin, $client));
        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->delete($admin, $client));
    }

    /**
     * Deny managers from managing API clients.
     */
    #[Test]
    public function it_denies_managers_from_managing_api_clients(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();
        $client = ApiClient::factory()->create();

        // Act + Assert

        $this->assertFalse($this->policy->viewAny($manager));
        $this->assertFalse($this->policy->view($manager, $client));
        $this->assertFalse($this->policy->create($manager));
        $this->assertFalse($this->policy->delete($manager, $client));
    }
}
