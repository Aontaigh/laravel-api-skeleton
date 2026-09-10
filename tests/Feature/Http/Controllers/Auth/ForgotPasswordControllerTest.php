<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Support\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the forgot-password endpoint.
 *
 * The endpoint must never reveal whether an address exists: every request
 * path that survives validation returns the same generic success envelope.
 */
#[CoversClass(ForgotPasswordController::class)]
#[CoversClass(ForgotPasswordRequest::class)]
#[CoversClass(ResetPasswordNotification::class)]
#[CoversClass(ApiResponse::class)]
final class ForgotPasswordControllerTest extends TestCase
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
     * Intercept notifications so delivery is asserted, not performed.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /*
     * Mutation Tests
     * --------------
     */

    /**
     * Send a reset link when the address belongs to an account.
     */
    #[Test]
    public function it_sends_a_reset_link_to_an_existing_account(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('message', 'If the Account Exists, a Reset Link Has Been Sent');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    /**
     * Return the identical envelope when the address is unknown.
     */
    #[Test]
    public function it_returns_the_generic_response_for_an_unknown_account(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('message', 'If the Account Exists, a Reset Link Has Been Sent');

        Notification::assertNothingSent();
    }

    /**
     * Soft-deleted accounts must not receive a reset link.
     */
    #[Test]
    public function it_does_not_send_a_reset_link_for_a_soft_deleted_account(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();
        $user->delete();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        // Assert

        $response->assertOk();

        Notification::assertNothingSent();
    }

    /*
     * Audit Tests
     * -----------
     */

    /**
     * Record the request in the auth audit log for known and unknown addresses.
     */
    #[Test]
    public function it_records_a_password_reset_requested_audit_event(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        // Act

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);
        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com']);

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => 'Password Reset Requested',
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => 'Password Reset Requested',
            'user_id' => null,
            'email' => 'nobody@example.com',
        ]);
    }

    /*
     * Rate Limiting Tests
     * -------------------
     */

    /**
     * Apply the broker's per-address throttle without changing the response.
     */
    #[Test]
    public function it_throttles_repeat_reset_link_requests(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        // Act

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);

        /** @var TestResponse<JsonResponse> $second */
        $second = $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);

        // Assert

        $second->assertOk();
        $second->assertJsonPath('message', 'If the Account Exists, a Reset Link Has Been Sent');

        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
    }

    /*
     * Validation Tests
     * ----------------
     */

    /**
     * Reject requests whose email field fails validation.
     *
     * @param array<string, string> $payload the malformed request body
     */
    #[DataProvider('invalidEmailProvider')]
    #[Test]
    public function it_rejects_an_invalid_email_field(array $payload): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/forgot-password', $payload);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['email']);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Return invalid email payloads.
     *
     * @return array<string, array<int, array<string, string>>> the data provider
     */
    public static function invalidEmailProvider(): array
    {
        return [
            'missing email' => [[]],
            'not an email' => [['email' => 'not-an-address']],
        ];
    }
}
