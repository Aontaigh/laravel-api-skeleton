<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Providers\AppServiceProvider;
use App\Support\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Proves the auth rate-limit keys tolerate malformed credential fields.
 *
 * The limiters run in middleware before validation, so a credential sent as
 * an array or object reaches the key builder as a non-scalar. The builder
 * must degrade that to the per-IP bucket rather than throw, or one malformed
 * request 500s the login, register, password-reset, and exchange endpoints.
 */
#[CoversClass(ApiResponse::class)]
#[CoversClass(AppServiceProvider::class)]
final class AuthRateLimitKeySafetyTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Answer validation, not a server error, when the login e-mail is an array.
     */
    #[Test]
    public function it_answers_validation_for_an_array_login_email(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login', [
            'email' => ['attacker@example.com'],
            'password' => 'SecretPass12',
        ]);

        // Assert

        $response->assertUnprocessable();
    }

    /**
     * Answer validation, not a server error, when the login e-mail is an object.
     */
    #[Test]
    public function it_answers_validation_for_an_object_login_email(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/login', [
            'email' => ['nested' => 'attacker@example.com'],
            'password' => 'SecretPass12',
        ]);

        // Assert

        $response->assertUnprocessable();
    }

    /**
     * Answer validation, not a server error, when the register e-mail is an array.
     */
    #[Test]
    public function it_answers_validation_for_an_array_register_email(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Attacker',
            'email' => ['attacker@example.com'],
            'password' => 'SecretPass12',
            'password_confirmation' => 'SecretPass12',
        ]);

        // Assert

        $response->assertUnprocessable();
    }

    /**
     * Answer validation, not a server error, when the forgot-password e-mail is an array.
     */
    #[Test]
    public function it_answers_validation_for_an_array_forgot_password_email(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => ['attacker@example.com'],
        ]);

        // Assert

        $response->assertUnprocessable();
    }

    /**
     * Answer validation, not a server error, when the exchange client ID is an array.
     */
    #[Test]
    public function it_answers_validation_for_an_array_exchange_client_id(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => ['not-a-string'],
            'client_secret' => 'not-a-string',
        ]);

        // Assert

        $response->assertUnprocessable();
    }
}
