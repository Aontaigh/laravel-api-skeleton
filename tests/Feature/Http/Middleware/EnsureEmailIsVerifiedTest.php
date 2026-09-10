<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the `email.verified` middleware gate.
 *
 * Business routes reject an unverified account with a 403 while the identity
 * exemptions (logout, `GET /me`, ending the current session) stay reachable,
 * so the SPA can route the account to the verification screen.
 */
#[CoversClass(\App\Http\Middleware\EnsureEmailIsVerified::class)]
final class EnsureEmailIsVerifiedTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Seed roles so the Admin factory state can assign its role.
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
     * Reject an unverified account on a business route with a 403.
     */
    #[Test]
    public function it_blocks_an_unverified_account_from_business_routes(): void
    {
        // Arrange

        $user = User::factory()->unverified()->create();

        // Act

        $response = $this->actingAs($user)->getJson('/api/sessions');

        // Assert

        $response->assertForbidden();
    }

    /**
     * Let a verified account through to the same business route.
     */
    #[Test]
    public function it_admits_a_verified_account_to_business_routes(): void
    {
        // Arrange

        $user = User::factory()->admin()->create();

        // Act

        $response = $this->actingAs($user)->getJson('/api/sessions');

        // Assert

        $response->assertOk();
    }

    /**
     * Keep `GET /me` reachable for an unverified account so the SPA can route
     * to the verification screen.
     */
    #[Test]
    public function it_exempts_the_profile_endpoint_for_unverified_accounts(): void
    {
        // Arrange

        $user = User::factory()->unverified()->create();

        // Act

        $response = $this->actingAs($user)->getJson('/api/me');

        // Assert

        $response->assertOk();
    }

    /**
     * Keep `POST /logout` reachable for an unverified account.
     */
    #[Test]
    public function it_exempts_logout_for_unverified_accounts(): void
    {
        // Arrange

        $user = User::factory()->unverified()->create();

        // Act

        $response = $this->actingAs($user)->postJson('/api/logout');

        // Assert

        $response->assertOk();
    }

    /**
     * Keep ending the current registry session reachable for an unverified
     * account - the same privilege as logout.
     */
    #[Test]
    public function it_exempts_ending_the_current_session_for_unverified_accounts(): void
    {
        // Arrange

        $user = User::factory()->unverified()->create();
        $user->assignRole(RoleName::User->value);
        $this->actingAs($user)->getJson('/api/me');

        // Act

        $response = $this->actingAs($user)->deleteJson('/api/sessions/current');
        // Assert

        /*
         * A token caller has no browser session to end, so the controller
         * answers 404 - the point is that the 403 `E-Mail Not Verified` gate
         * never fires on this route.
         */
        $response->assertNotFound();
    }

    /**
     * Gate the password change behind verification: an account that has not
     * proven e-mail ownership must not rotate credentials.
     */
    #[Test]
    public function it_blocks_the_password_change_for_unverified_accounts(): void
    {
        // Arrange

        $user = User::factory()->unverified()->create([
            'password' => 'CurrentPass12',
        ]);

        // Act

        $response = $this->actingAs($user)->patchJson('/api/me/password', [
            'current_password' => 'CurrentPass12',
            'password' => 'NewSecretPass13',
            'password_confirmation' => 'NewSecretPass13',
        ]);

        // Assert

        $response->assertForbidden();
    }

    /**
     * Exempt service accounts: they authenticate via client credentials, never
     * email, so there is no mailbox to verify - blocking them would disable
     * machine-to-machine access entirely.
     */
    #[Test]
    public function it_exempts_service_accounts_from_verification(): void
    {
        // Arrange

        $serviceUser = User::factory()->serviceAccount()->service()->unverified()->create();

        // Act

        $response = $this->actingAs($serviceUser)->getJson('/api/roles');

        // Assert

        $response->assertOk();
    }
}
