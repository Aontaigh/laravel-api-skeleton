<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Actions\Auth\ApplyRememberMeAction;
use App\Actions\Auth\AuthenticateUserAction;
use App\Actions\Auth\FinaliseAuthenticatedSessionAction;
use App\Actions\Auth\RecordAuthAuditAction;
use App\Actions\Tokens\CreatePersonalAccessTokenAction;
use App\DataTransferObjects\Auth\FinaliseAuthenticatedSessionData;
use App\DataTransferObjects\Auth\LoginCredentialsData;
use App\Enums\AuthAuditEvent;
use App\Enums\MfaMethod;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Models\User;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\MakesStatefulSpaRequests;
use Tests\TestCase;

/**
 * Feature tests for password-based login.
 */
#[CoversClass(LoginController::class)]
#[CoversClass(LoginRequest::class)]
#[CoversClass(FinaliseAuthenticatedSessionAction::class)]
#[CoversClass(FinaliseAuthenticatedSessionData::class)]
#[CoversClass(ApplyRememberMeAction::class)]
#[CoversClass(RecordAuthAuditAction::class)]
#[CoversClass(AuthenticateUserAction::class)]
#[CoversClass(CreatePersonalAccessTokenAction::class)]
#[CoversClass(LoginCredentialsData::class)]
#[CoversClass(AuthenticatedUserResource::class)]
#[CoversClass(PersonalAccessTokenResource::class)]
#[CoversClass(ApiResponse::class)]
final class LoginControllerTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use MakesStatefulSpaRequests;
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Seed permissions for token issuance.
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
     * Response Structure Tests
     * --------------------------
     */

    /**
     * Return the standard success envelope with user, token, and plaintext value.
     */
    #[Test]
    public function it_returns_the_standard_success_envelope(): void
    {
        // Arrange

        User::factory()->user()->create([
            'email' => 'alice@example.com',
            'password' => Hash::make('Xq7#mK2$vL9pTzW4'),
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'device_name' => 'Mobile App',
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('status_code', 200);
        $response->assertJsonPath('message', 'Logged In Successfully');
        $response->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email', 'created_at'],
                'token' => ['id', 'name', 'abilities', 'expires_at', 'created_at'],
                'plain_text_token',
            ],
            'meta',
        ]);
        $response->assertJsonPath('data.token.name', 'Mobile App');
        $this->assertNotEmpty($response->json('data.plain_text_token'));
    }

    /*
     * Authentication Tests
     * --------------------
     */

    /**
     * Log in with valid credentials and return a bearer token.
     */
    #[Test]
    public function it_logs_in_with_valid_credentials(): void
    {
        // Arrange

        User::factory()->user()->create([
            'email' => 'alice@example.com',
            'password' => Hash::make('Xq7#mK2$vL9pTzW4'),
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.user.email', 'alice@example.com');
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'API Session',
            'tokenable_id' => User::query()->where('email', 'alice@example.com')->value('id'),
        ]);
        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::Login->value,
            'email' => 'alice@example.com',
            'remember_me' => false,
        ]);
    }

    /**
     * Strip markup from the Sanctum token device name.
     */
    #[Test]
    public function it_strips_markup_from_the_device_name(): void
    {
        // Arrange

        User::factory()->user()->create([
            'email' => 'alice@example.com',
            'password' => Hash::make('Xq7#mK2$vL9pTzW4'),
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'device_name' => '<script>alert(1)</script>Mobile App',
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.token.name', 'alert(1)Mobile App');
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'alert(1)Mobile App',
        ]);
    }

    /**
     * Apply remember-me state and issue a longer-lived token when requested.
     */
    #[Test]
    public function it_applies_remember_me_when_requested(): void
    {
        // Arrange

        Carbon::setTestNow('2026-01-01 12:00:00');

        User::factory()->user()->create([
            'email' => 'alice@example.com',
            'password' => Hash::make('Xq7#mK2$vL9pTzW4'),
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'remember' => true,
        ]);

        // Assert

        $response->assertOk();
        $this->assertNotNull(
            User::query()->where('email', 'alice@example.com')->value('remember_token')
        );
        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::Login->value,
            'email' => 'alice@example.com',
            'remember_me' => true,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'API Session',
            'expires_at' => '2027-01-01 12:00:00',
        ]);

        Carbon::setTestNow();
    }

    /**
     * Queue the remember-me recaller cookie on stateful SPA login.
     */
    #[Test]
    public function it_sets_the_remember_me_cookie_on_stateful_login(): void
    {
        // Arrange

        User::factory()->user()->create([
            'email' => 'alice@example.com',
            'password' => Hash::make('Xq7#mK2$vL9pTzW4'),
        ]);

        $statefulHeaders = $this->statefulRequestHeaders();

        $xsrfToken = $this->beginStatefulSession($statefulHeaders);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withCredentials()
            ->withHeaders($this->statefulRequestHeaders($xsrfToken))
            ->postJson('/api/auth/login', [
                'email' => 'alice@example.com',
                'password' => 'Xq7#mK2$vL9pTzW4',
                'remember' => true,
            ]);

        // Assert

        $response->assertOk();
        $response->assertCookie($this->webGuard()->getRecallerName());
    }

    /**
     * Restore a session from the remember-me cookie after the session cookie expires.
     */
    #[Test]
    public function it_restores_a_session_from_the_remember_me_cookie(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create([
            'email' => 'alice@example.com',
            'password' => Hash::make('Xq7#mK2$vL9pTzW4'),
        ]);

        $statefulHeaders = $this->statefulRequestHeaders();

        $xsrfToken = $this->beginStatefulSession($statefulHeaders);

        /** @var TestResponse<JsonResponse> $loginResponse */
        $loginResponse = $this->withCredentials()
            ->withHeaders($this->statefulRequestHeaders($xsrfToken))
            ->postJson('/api/auth/login', [
                'email' => 'alice@example.com',
                'password' => 'Xq7#mK2$vL9pTzW4',
                'remember' => true,
            ]);

        $loginResponse->assertOk();

        $recallerName = $this->webGuard()->getRecallerName();
        $recallerCookie = $loginResponse->getCookie($recallerName);
        $this->assertNotNull($recallerCookie);
        $recallerValue = $this->requireNonEmptyString(
            $recallerCookie->getValue(),
            'Remember cookie value missing',
        );

        $this->flushSession();

        $freshXsrfToken = $this->beginStatefulSession($statefulHeaders);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withCredentials()
            ->withCookie($recallerName, $recallerValue)
            ->withHeaders($this->statefulRequestHeaders($freshXsrfToken))
            ->postJson('/api/auth/login/remember');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.user.id', $user->id);
    }

    /**
     * Record a failed login attempt in the audit log.
     */
    #[Test]
    public function it_records_a_failed_login_attempt_in_the_audit_log(): void
    {
        // Act

        $this->postJson('/api/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ])->assertUnprocessable();

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::LoginFailed->value,
            'email' => 'missing@example.com',
            'user_id' => null,
        ]);
    }

    /**
     * Match credentials case-insensitively on the email address.
     */
    #[Test]
    public function it_matches_credentials_case_insensitively_on_email(): void
    {
        // Arrange

        User::factory()->user()->create([
            'email' => 'alice@example.com',
            'password' => Hash::make('Xq7#mK2$vL9pTzW4'),
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login', [
            'email' => 'ALICE@EXAMPLE.COM',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.user.email', 'alice@example.com');
    }

    /**
     * Reject invalid credentials with a generic validation message.
     */
    #[Test]
    public function it_rejects_invalid_credentials_with_a_generic_message(): void
    {
        // Arrange

        User::factory()->user()->create([
            'email' => 'alice@example.com',
            'password' => Hash::make('Xq7#mK2$vL9pTzW4'),
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'WrongPass1',
        ]);

        // Assert

        $this->assertApiValidationErrors($response, ['email']);
        $response->assertJsonPath('meta.errors.email.0', 'Invalid Credentials');
    }

    /**
     * Reject soft-deleted accounts with the same generic message.
     */
    #[Test]
    public function it_rejects_soft_deleted_accounts(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->user()->create([
            'email' => 'alice@example.com',
            'password' => Hash::make('Xq7#mK2$vL9pTzW4'),
        ]);
        $user->delete();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertJsonPath('meta.errors.email.0', 'Invalid Credentials');
    }

    /**
     * Reject suspended accounts with the same generic message as invalid credentials.
     */
    #[Test]
    public function it_rejects_suspended_accounts_with_a_generic_message(): void
    {
        // Arrange

        User::factory()->user()->suspended()->create([
            'email' => 'alice@example.com',
            'password' => Hash::make('Xq7#mK2$vL9pTzW4'),
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $this->assertApiValidationErrors($response, ['email']);
        $response->assertJsonPath('meta.errors.email.0', 'Invalid Credentials');
    }

    /**
     * Reject suspended MFA-enrolled accounts before opening a two-factor challenge.
     */
    #[Test]
    public function it_rejects_suspended_mfa_enrolled_accounts_before_two_factor(): void
    {
        // Arrange

        User::factory()->user()->suspended()->create([
            'email' => 'mfa-suspended@example.com',
            'password' => Hash::make('Xq7#mK2$vL9pTzW4'),
            'mfa_method' => MfaMethod::Email,
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login', [
            'email' => 'mfa-suspended@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $this->assertApiValidationErrors($response, ['email']);
        $response->assertJsonPath('meta.errors.email.0', 'Invalid Credentials');
        $response->assertJsonMissingPath('data.two_factor_required');
        $response->assertJsonMissingPath('data.two_factor_token');
    }

    /**
     * Return the same generic message for unknown emails and wrong passwords.
     */
    #[Test]
    public function it_returns_the_same_generic_message_for_unknown_emails_and_wrong_passwords(): void
    {
        // Arrange

        User::factory()->user()->create([
            'email' => 'alice@example.com',
            'password' => Hash::make('Xq7#mK2$vL9pTzW4'),
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $unknownEmailResponse */
        $unknownEmailResponse = $this->postJson('/api/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
        ]);

        /** @var TestResponse<JsonResponse> $wrongPasswordResponse */
        $wrongPasswordResponse = $this->postJson('/api/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'WrongPass1',
        ]);

        // Assert

        $unknownMessage = $unknownEmailResponse->json('meta.errors.email.0');
        $wrongPasswordMessage = $wrongPasswordResponse->json('meta.errors.email.0');
        $this->assertSame('Invalid Credentials', $unknownMessage);
        $this->assertSame($unknownMessage, $wrongPasswordMessage);
    }

    /*
     * Validation Tests
     * ----------------
     */

    /**
     * Reject hostile and incomplete login payloads.
     *
     * @param array<string, mixed> $payload
     * @param list<string>         $expectedErrorKeys
     */
    #[Test]
    #[DataProvider('invalidLoginPayloadProvider')]
    public function it_rejects_invalid_login_payloads(array $payload, array $expectedErrorKeys): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login', $payload);

        // Assert

        $this->assertApiValidationErrors($response, $expectedErrorKeys);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Incomplete or malformed login payloads.
     *
     * @return array<string, array{0: array<string, mixed>, 1: list<string>}>
     */
    public static function invalidLoginPayloadProvider(): array
    {
        return [
            'missing email' => [
                ['password' => 'Xq7#mK2$vL9pTzW4'],
                ['email'],
            ],
            'missing password' => [
                ['email' => 'alice@example.com'],
                ['password'],
            ],
            'invalid email format' => [
                ['email' => 'not-an-email', 'password' => 'Xq7#mK2$vL9pTzW4'],
                ['email'],
            ],
            'empty password' => [
                ['email' => 'alice@example.com', 'password' => ''],
                ['password'],
            ],
            'oversized device name' => [
                [
                    'email' => 'alice@example.com',
                    'password' => 'Xq7#mK2$vL9pTzW4',
                    'device_name' => str_repeat('a', 256),
                ],
                ['device_name'],
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the web session guard for remember-me cookie assertions.
     */
    private function webGuard(): SessionGuard
    {
        /** @var SessionGuard $guard */
        $guard = Auth::guard('web');

        return $guard;
    }
}
