<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Actions\Auth\RecordAuthAuditAction;
use App\Actions\Auth\RegisterUserAction;
use App\DataTransferObjects\Auth\RegisterUserData;
use App\Enums\AuthAuditEvent;
use App\Enums\RoleName;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Auth\PendingTwoFactor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for self-service registration.
 */
#[CoversClass(RegisterController::class)]
#[CoversClass(RegisterRequest::class)]
#[CoversClass(RecordAuthAuditAction::class)]
#[CoversClass(RegisterUserAction::class)]
#[CoversClass(RegisterUserData::class)]
#[CoversClass(AuthenticatedUserResource::class)]
#[CoversClass(PersonalAccessTokenResource::class)]
#[CoversClass(PendingTwoFactor::class)]
#[CoversClass(ApiResponse::class)]
final class RegisterControllerTest extends TestCase
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
     * Seed roles before registration assigns the User role.
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
     * Return the pending two-factor envelope after account creation.
     */
    #[Test]
    public function it_returns_the_standard_success_envelope(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/register', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertCreated();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('status_code', 201);
        $response->assertJsonPath('message', 'Account Created Successfully');
        $response->assertJsonStructure([
            'data' => ['two_factor_required', 'two_factor_token'],
            'meta',
        ]);
        $response->assertJsonPath('data.two_factor_required', true);
        $response->assertJsonMissingPath('data.user');
        $response->assertJsonMissingPath('data.token');
    }

    /*
     * Mutation Tests
     * --------------
     */

    /**
     * Register a User and return a bearer token after two-factor verification.
     */
    #[Test]
    public function it_registers_a_user_and_returns_a_bearer_token_after_two_factor_verification(): void
    {
        // Arrange

        Notification::fake();

        /** @var TestResponse<JsonResponse> $register */
        $register = $this->postJson('/api/register', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
            'device_name' => 'Mobile App',
        ]);

        $twoFactorToken = $register->json('data.two_factor_token');
        $this->assertIsString($twoFactorToken);

        $this->postJson('/api/two-factor/send', [
            'channel' => 'email',
            'two_factor_token' => $twoFactorToken,
        ]);

        /** @var User $user */
        $user = User::query()->where('email', 'alice@example.com')->firstOrFail();

        $code = '123456';
        Cache::put('two-factor:'.$user->id, [
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ], 300);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/two-factor/verify', [
            'code' => $code,
            'device_name' => 'Mobile App',
            'two_factor_token' => $twoFactorToken,
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.user.email', 'alice@example.com');
        $this->assertNotEmpty($response->json('data.plain_text_token'));

        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
            'team_id' => null,
        ]);

        $this->assertTrue($user->refresh()->hasRole(RoleName::User->value));
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'Mobile App',
            'tokenable_id' => $user->id,
        ]);
        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::Register->value,
            'user_id' => $user->id,
            'email' => 'alice@example.com',
        ]);
        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::Login->value,
            'user_id' => $user->id,
            'email' => 'alice@example.com',
        ]);
    }

    /**
     * Store the email address in lowercase.
     */
    #[Test]
    public function it_stores_the_email_address_in_lowercase(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/register', [
            'name' => 'Alice',
            'email' => 'ALICE@EXAMPLE.COM',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
    }

    /**
     * Strip markup from the display name.
     */
    #[Test]
    public function it_strips_markup_from_the_display_name(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/register', [
            'name' => '<script>alert(1)</script>Alice',
            'email' => 'alice@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
            'name' => 'alert(1)Alice',
        ]);
    }

    /**
     * Ignore mass-assignment fields that are not part of registration.
     */
    #[Test]
    public function it_ignores_mass_assignment_fields_outside_registration(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/register', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
            'email_verified_at' => '2026-01-01T00:00:00Z',
            'is_admin' => true,
            'team_id' => 1,
        ]);

        // Assert

        $response->assertCreated();

        /** @var User $user */
        $user = User::query()->where('email', 'alice@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->team_id);
        $this->assertFalse($user->hasRole(RoleName::Admin->value));
        $this->assertTrue($user->hasRole(RoleName::User->value));
    }

    /*
     * Validation Tests
     * ----------------
     */

    /**
     * Reject duplicate email addresses.
     */
    #[Test]
    public function it_rejects_a_duplicate_email_address(): void
    {
        // Arrange

        User::factory()->create(['email' => 'alice@example.com']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/register', [
            'name' => 'Alice Two',
            'email' => 'alice@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $this->assertApiValidationErrors($response, ['email']);
        $response->assertJsonPath('meta.errors.email.0', 'Invalid Credentials');
    }

    /**
     * Reject duplicate emails with the same generic message used at login.
     */
    #[Test]
    public function it_does_not_reveal_account_existence_on_duplicate_email(): void
    {
        // Arrange

        User::factory()->create(['email' => 'alice@example.com']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/register', [
            'name' => 'Probe',
            'email' => 'alice@example.com',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $this->assertApiValidationErrors($response, ['email']);
        $response->assertJsonPath('meta.errors.email.0', 'Invalid Credentials');
    }

    /**
     * Reject case-variant duplicates after normalising the email address.
     */
    #[Test]
    public function it_rejects_case_variant_duplicates_with_a_generic_message(): void
    {
        // Arrange

        User::factory()->create(['email' => 'alice@example.com']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/register', [
            'name' => 'Alice Two',
            'email' => 'ALICE@EXAMPLE.COM',
            'password' => 'Xq7#mK2$vL9pTzW4',
            'password_confirmation' => 'Xq7#mK2$vL9pTzW4',
        ]);

        // Assert

        $this->assertApiValidationErrors($response, ['email']);
        $response->assertJsonPath('meta.errors.email.0', 'Invalid Credentials');
        $this->assertDatabaseMissing('users', ['email' => 'alice@example.com', 'name' => 'Alice Two']);
    }

    /**
     * Reject hostile and incomplete registration payloads.
     *
     * @param array<string, mixed> $payload
     * @param list<string>         $expectedErrorKeys
     */
    #[Test]
    #[DataProvider('invalidRegisterPayloadProvider')]
    public function it_rejects_invalid_register_payloads(array $payload, array $expectedErrorKeys): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/register', $payload);

        // Assert

        $this->assertApiValidationErrors($response, $expectedErrorKeys);

        if (isset($payload['email']) && is_string($payload['email']) && str_contains($payload['email'], '@')) {
            $this->assertDatabaseMissing('users', ['email' => strtolower($payload['email'])]);
        }
    }

    /**
     * Reject passwords that fail the configured complexity rules.
     */
    #[Test]
    #[DataProvider('weakPasswordProvider')]
    public function it_rejects_weak_passwords(string $password): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/register', [
            'name' => 'Alice',
            'email' => 'weak@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        // Assert

        $this->assertApiValidationErrors($response, ['password']);
        $this->assertDatabaseMissing('users', ['email' => 'weak@example.com']);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Incomplete or malformed registration payloads.
     *
     * @return array<string, array{0: array<string, mixed>, 1: list<string>}>
     */
    public static function invalidRegisterPayloadProvider(): array
    {
        $validPassword = 'Xq7#mK2$vL9pTzW4';

        return [
            'missing name' => [
                [
                    'email' => 'missing-name@example.com',
                    'password' => $validPassword,
                    'password_confirmation' => $validPassword,
                ],
                ['name'],
            ],
            'missing email' => [
                [
                    'name' => 'Alice',
                    'password' => $validPassword,
                    'password_confirmation' => $validPassword,
                ],
                ['email'],
            ],
            'missing password' => [
                [
                    'name' => 'Alice',
                    'email' => 'missing-password@example.com',
                    'password_confirmation' => $validPassword,
                ],
                ['password'],
            ],
            'missing confirmation' => [
                [
                    'name' => 'Alice',
                    'email' => 'missing-confirmation@example.com',
                    'password' => $validPassword,
                ],
                ['password'],
            ],
            'password mismatch' => [
                [
                    'name' => 'Alice',
                    'email' => 'mismatch@example.com',
                    'password' => $validPassword,
                    'password_confirmation' => 'OtherPass12',
                ],
                ['password'],
            ],
            'invalid email format' => [
                [
                    'name' => 'Alice',
                    'email' => 'not-an-email',
                    'password' => $validPassword,
                    'password_confirmation' => $validPassword,
                ],
                ['email'],
            ],
            'oversized name' => [
                [
                    'name' => str_repeat('a', 256),
                    'email' => 'long-name@example.com',
                    'password' => $validPassword,
                    'password_confirmation' => $validPassword,
                ],
                ['name'],
            ],
        ];
    }

    /**
     * Password values that violate the registration complexity rules.
     *
     * @return array<string, array{0: string}>
     */
    public static function weakPasswordProvider(): array
    {
        return [
            'too short' => ['short'],
            'no mixed case' => ['secretpass12'],
            'no numbers' => ['SecretPassword'],
            'breached (HaveIBeenPwned)' => ['Password123'],
        ];
    }
}
