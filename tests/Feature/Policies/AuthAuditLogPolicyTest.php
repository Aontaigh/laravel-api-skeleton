<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\AuthAuditLog;
use App\Models\User;
use App\Policies\AuthAuditLogPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for AuthAuditLogPolicy.
 */
#[CoversClass(AuthAuditLogPolicy::class)]
final class AuthAuditLogPolicyTest extends TestCase
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

    private AuthAuditLogPolicy $policy;

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
        $this->policy = new AuthAuditLogPolicy;
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Allow admins to list and view auth audit logs.
     */
    #[Test]
    public function it_allows_admins_to_list_and_view_auth_audit_logs(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $log = AuthAuditLog::factory()->for($admin)->create();

        // Act + Assert

        $this->assertTrue($this->policy->viewAny($admin));
        $this->assertTrue($this->policy->view($admin, $log));
    }

    /**
     * Deny managers without audit log list permission.
     */
    #[Test]
    public function it_denies_managers_without_audit_log_list_permission(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();
        $log = AuthAuditLog::factory()->for($manager)->create();

        // Act + Assert

        $this->assertFalse($this->policy->viewAny($manager));
        $this->assertFalse($this->policy->view($manager, $log));
    }

    /**
     * Deny regular users without the Admin role.
     */
    #[Test]
    public function it_denies_regular_users(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create();
        $log = AuthAuditLog::factory()->create();

        // Act + Assert

        $this->assertFalse($this->policy->viewAny($user));
        $this->assertFalse($this->policy->view($user, $log));
    }

    /**
     * Deny service accounts even when they hold other list permissions.
     */
    #[Test]
    public function it_denies_service_accounts(): void
    {
        // Arrange

        /** @var User $serviceUser */
        $serviceUser = User::factory()->serviceAccount()->create();
        $log = AuthAuditLog::factory()->create();

        // Act + Assert

        $this->assertFalse($this->policy->viewAny($serviceUser));
        $this->assertFalse($this->policy->view($serviceUser, $log));
    }
}
