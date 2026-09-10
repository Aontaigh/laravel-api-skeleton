<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Users;

use App\Actions\Users\UpdatePasswordAction;
use App\DataTransferObjects\Users\UpdatePasswordData;
use App\Enums\PasswordChangeSource;
use App\Http\Controllers\Users\UpdateMePasswordController;
use App\Http\Requests\Users\UpdateMePasswordRequest;
use App\Models\User;
use App\Notifications\Auth\PasswordChangedNotification;
use App\Policies\UserPolicy;
use App\Support\ApiResponse;
use App\Support\Auth\PasswordMaxLength;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesBreachLookup;
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
#[CoversClass(PasswordMaxLength::class)]
final class UpdateMePasswordControllerTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use FakesBreachLookup;
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
        $this->fakeBreachLookup(['Password123']);
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
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        /* Assert */

        $response->assertOk();
        $response->assertJsonPath('message', 'Password Updated Successfully');

        /* The new password must actually work. */
        $user->refresh();

        $this->assertTrue(Hash::check('Xq7#mK2$vL9pTzW4', $user->password));
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
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
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
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
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
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        /* Assert */

        $response->assertForbidden();
    }

    /**
     * Reject a new password that passes the old floor but fails the shared policy.
     *
     * Regression: the change request historically accepted `min:8` while
     * registration enforced `Password::defaults()`, so a User could weaken
     * their own password below the registration contract.
     */
    #[Test]
    public function it_rejects_a_new_password_that_fails_the_shared_policy(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create([
            'password' => Hash::make('Xq7#mK2$vL9pTzW4'),
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->patchJson('/api/me/password', [
            'current_password' => 'Xq7#mK2$vL9pTzW4',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['password']);
    }

    /**
     * Reject a new password identical to the current one.
     */
    #[Test]
    public function it_rejects_a_new_password_matching_the_current_one(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create([
            'password' => Hash::make('Xq7#mK2$vL9pTzW4'),
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->patchJson('/api/me/password', [
            'current_password' => 'Xq7#mK2$vL9pTzW4',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['password']);
    }

    /**
     * Queue the password-changed security alert after a successful change.
     */
    #[Test]
    public function it_sends_a_password_changed_notification_on_success(): void
    {
        // Arrange

        Notification::fake();

        /** @var User $user */
        $user = User::factory()->user()->create([
            'password' => Hash::make('current-password'),
        ]);

        // Act

        $this->actingAs($user)->patchJson('/api/me/password', [
            'current_password' => 'current-password',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        Notification::assertSentTo($user, PasswordChangedNotification::class);
        Notification::assertSentTo($user, PasswordChangedNotification::class, function (PasswordChangedNotification $notification): bool {
            return $notification->source === PasswordChangeSource::SelfService;
        });
    }

    /**
     * Never queue the alert when the change fails validation.
     */
    #[Test]
    public function it_does_not_send_a_notification_when_the_change_fails(): void
    {
        // Arrange

        Notification::fake();

        /** @var User $user */
        $user = User::factory()->user()->create([
            'password' => Hash::make('current-password'),
        ]);

        // Act

        $this->actingAs($user)->patchJson('/api/me/password', [
            'current_password' => 'wrong-password',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        Notification::assertNotSentTo($user, PasswordChangedNotification::class);
    }
}
