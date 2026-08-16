<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Users;

use App\Actions\Users\UpdatePasswordAction;
use App\DataTransferObjects\Users\UpdatePasswordData;
use App\Http\Controllers\Users\UpdateMePasswordController;
use App\Http\Requests\Users\UpdateMePasswordRequest;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the self-service password change endpoint.
 */
#[CoversClass(UpdateMePasswordController::class)]
#[CoversClass(UpdateMePasswordRequest::class)]
#[CoversClass(UpdatePasswordAction::class)]
#[CoversClass(UpdatePasswordData::class)]
#[CoversClass(UserPolicy::class)]
#[CoversClass(ApiResponse::class)]
final class UpdateMePasswordControllerTest extends TestCase
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

    /*
     * Update Tests
     * ------------
     */

    /**
     * Change the caller's own password.
     */
    #[Test]
    public function it_changes_the_callers_own_password(): void
    {
        /* Arrange */

        /** @var User $user */
        $user = User::factory()->user()->create([
            'password' => Hash::make('current-password'),
        ]);

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->patchJson('/api/me/password', [
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        /* Assert */

        $response->assertOk();
        $response->assertJsonPath('message', 'Password Updated Successfully');

        /* The new password must actually work. */
        $user->refresh();

        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    /**
     * Reject a wrong current password with a generic message.
     */
    #[Test]
    public function it_rejects_a_wrong_current_password(): void
    {
        /* Arrange */

        /** @var User $user */
        $user = User::factory()->user()->create([
            'password' => Hash::make('correct-password'),
        ]);

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->patchJson('/api/me/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        /* Assert */

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['current_password']);
    }

    /**
     * Reject a new password that does not meet strength requirements.
     */
    #[Test]
    public function it_rejects_a_new_password_that_does_not_meet_strength_requirements(): void
    {
        /* Arrange */

        /** @var User $user */
        $user = User::factory()->user()->create([
            'password' => Hash::make('current-password'),
        ]);

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->patchJson('/api/me/password', [
            'current_password' => 'current-password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        /* Assert */

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['password']);
    }

    /*
     * Authorisation Tests
     * -------------------
     */

    /**
     * Deny unauthenticated requests.
     */
    #[Test]
    public function it_denies_unauthenticated_requests(): void
    {
        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->patchJson('/api/me/password', [
            'current_password' => 'anything',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        /* Assert */

        $response->assertUnauthorized();
    }

    /**
     * Deny service accounts from self-service password changes.
     */
    #[Test]
    public function it_denies_service_accounts_from_self_service_password_changes(): void
    {
        /* Arrange */

        /** @var User $serviceUser */
        $serviceUser = User::factory()->serviceAccount()->service()->create([
            'password' => Hash::make('secret'),
        ]);

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($serviceUser)->patchJson('/api/me/password', [
            'current_password' => 'secret',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        /* Assert */

        $response->assertForbidden();
    }
}
