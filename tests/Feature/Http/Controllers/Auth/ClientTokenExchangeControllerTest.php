<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateClientCredentialsAction;
use App\Actions\Auth\ExchangeClientCredentialsAction;
use App\Actions\Auth\RecordAuthAuditAction;
use App\Actions\Tokens\CreatePersonalAccessTokenAction;
use App\DataTransferObjects\Auth\ClientCredentialsData;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\DataTransferObjects\Tokens\CreateTokenData;
use App\Enums\AuditOutcome;
use App\Enums\AuthAuditEvent;
use App\Enums\ClientIneligibilityReason;
use App\Http\Controllers\Auth\ClientTokenExchangeController;
use App\Http\Requests\Auth\ClientTokenExchangeRequest;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Models\ApiClient;
use App\Support\ApiResponse;
use Database\Seeders\ApiClientsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for OAuth2 client-credentials token exchange.
 */
#[CoversClass(ClientTokenExchangeController::class)]
#[CoversClass(ClientTokenExchangeRequest::class)]
#[CoversClass(ExchangeClientCredentialsAction::class)]
#[CoversClass(AuthenticateClientCredentialsAction::class)]
#[CoversClass(ClientCredentialsData::class)]
#[CoversClass(RecordAuthAuditData::class)]
#[CoversClass(RecordAuthAuditAction::class)]
#[CoversClass(CreatePersonalAccessTokenAction::class)]
#[CoversClass(CreateTokenData::class)]
#[CoversClass(PersonalAccessTokenResource::class)]
#[CoversClass(ApiResponse::class)]
final class ClientTokenExchangeControllerTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Setup / Teardown
    |--------------------------------------------------------------------------
    */

    /**
     * Seed permissions for token issuance on service accounts.
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

    /*
     * Response Structure Tests
     * ------------------------
     */

    /**
     * Issue a scoped token for valid client credentials.
     */
    #[Test]
    public function it_issues_a_scoped_token_for_valid_client_credentials(): void
    {
        // Arrange

        $plainSecret = 'ClientSecretValue12';
        $client = ApiClient::factory()->create([
            'client_secret' => Hash::make($plainSecret),
            'abilities' => ['users.list', 'users.list-all'],
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->client_id,
            'client_secret' => $plainSecret,
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Token Issued Successfully');
        $response->assertJsonStructure([
            'data' => [
                'token' => ['id', 'name', 'abilities', 'expires_at', 'created_at'],
                'plain_text_token',
                'expires_in',
            ],
        ]);
        $response->assertJsonPath('data.token.abilities', ['users.list', 'users.list-all']);

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::ClientTokenExchange->value,
            'api_client_id' => $client->id,
        ]);

        /** @var string $plainTextToken */
        $plainTextToken = $response->json('data.plain_text_token');

        $this->withToken($plainTextToken)
            ->getJson('/api/users')
            ->assertOk();
    }

    /**
     * Cap a client token at the maximum lifetime when the client lifetime is disabled.
     *
     * A configured zero asks for a token that never expires; the machine
     * credential contract forbids that, so the lifetime falls back to the
     * ceiling and the issued token still ages out.
     */
    #[Test]
    public function it_caps_a_client_token_at_the_maximum_lifetime_when_client_lifetime_is_zero(): void
    {
        // Arrange

        Carbon::setTestNow('2026-01-15 10:00:00');

        config([
            'api.client_token_expiration_days' => 0,
            'api.token_max_expiration_days' => 366,
        ]);

        $plainSecret = 'ClientSecretValue12';
        $client = ApiClient::factory()->create([
            'client_secret' => Hash::make($plainSecret),
            'abilities' => ['users.list'],
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->client_id,
            'client_secret' => $plainSecret,
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.expires_in', 366 * 24 * 60 * 60);
        $response->assertJsonPath('data.token.expires_at', '2027-01-16T10:00:00+00:00');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $client->user_id,
            'expires_at' => '2027-01-16 10:00:00',
        ]);

        Carbon::setTestNow();
    }

    /**
     * Exchange the seeded demo client credentials.
     */
    #[Test]
    public function it_exchanges_the_seeded_demo_client_credentials(): void
    {
        // Arrange

        $this->seed(ApiClientsSeeder::class);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => ApiClientsSeeder::DEMO_CLIENT_ID,
            'client_secret' => ApiClientsSeeder::DEMO_CLIENT_SECRET,
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.token.abilities', ['users.list', 'users.list-all', 'roles.list']);
    }

    /*
     * Validation Tests
     * ----------------
     */

    /**
     * Reject invalid client credentials with a generic message.
     */
    #[Test]
    public function it_rejects_invalid_client_credentials_with_a_generic_message(): void
    {
        // Arrange

        $client = ApiClient::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->client_id,
            'client_secret' => 'wrong-secret-value',
        ]);

        // Assert

        $response->assertBadRequest();
        $response->assertJsonPath('error', 'invalid_client');
        $response->assertJsonPath('error_description', 'Client Authentication Failed');

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::ClientTokenExchangeFailed->value,
        ]);
    }

    /**
     * Reject suspended service users with the same generic message as a wrong secret.
     */
    #[Test]
    public function it_rejects_suspended_service_users(): void
    {
        // Arrange

        $plainSecret = 'SuspendedSecret12';
        $client = ApiClient::factory()->create([
            'client_secret' => Hash::make($plainSecret),
        ]);
        $client->user->forceFill(['suspended_at' => now()])->save();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->client_id,
            'client_secret' => $plainSecret,
        ]);

        // Assert

        $response->assertBadRequest();
        $response->assertJsonPath('error', 'invalid_grant');
        $response->assertJsonPath('error_description', 'Client Authentication Failed');
    }

    /**
     * Reject soft-deleted service users with the same generic message as a wrong secret.
     *
     * A soft-deleted User is invisible to the default `BelongsTo` relation
     * (SoftDeletes hides deleted rows), so `$client->user` returns null
     * rather than the deleted model. Calling `isSuspended()` on null would
     * crash with a fatal error instead of returning the expected rejection.
     */
    #[Test]
    public function it_rejects_soft_deleted_service_users(): void
    {
        // Arrange

        $plainSecret = 'SoftDeletedSecret12';
        $client = ApiClient::factory()->create([
            'client_secret' => Hash::make($plainSecret),
        ]);
        $client->user->delete();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->client_id,
            'client_secret' => $plainSecret,
        ]);

        // Assert

        $response->assertBadRequest();
        $response->assertJsonPath('error', 'invalid_grant');
        $response->assertJsonPath('error_description', 'Client Authentication Failed');
    }

    /**
     * Reject inactive clients with the same generic message as a wrong secret.
     */
    /**
     * Audit a verified credential refused by policy as `refused`, with its reason.
     *
     * The action raised one `ValidationException` for every cause, so the controller
     * recorded each rejection as `failed`. A suspended service account and a mistyped
     * secret then produced audit rows differing only in their event name, and an
     * incident responder could not tell a deliberate policy decline from a credential
     * attack. Proved red first: the row carried `failed` with no reason.
     */
    #[Test]
    public function it_audits_a_suspended_owner_refusal_as_refused_with_its_reason(): void
    {
        // Arrange

        $plainSecret = 'RefusedSecret12';
        $client = ApiClient::factory()->create(['client_secret' => Hash::make($plainSecret)]);
        $client->user->forceFill(['suspended_at' => now()])->save();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->client_id,
            'client_secret' => $plainSecret,
        ]);

        // Assert

        $response->assertBadRequest();
        $response->assertJsonPath('error', 'invalid_grant');
        $response->assertJsonPath('error_description', 'Client Authentication Failed');

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::ClientTokenExchangeFailed->value,
            'outcome' => AuditOutcome::Refused->value,
            'client_ineligibility_reason' => ClientIneligibilityReason::SuspendedOwner->value,
            'user_id' => $client->user_id,
            'api_client_id' => $client->id,
        ]);
    }

    /**
     * Attribute no principal when the secret does not verify.
     *
     * Each of these fails before the credential proves out, so naming a principal
     * would turn the audit log into an account-enumeration oracle: an attacker learns
     * which client IDs and owners exist by reading outcomes back. The external
     * response is identical either way, so the audit log must be too.
     */
    #[Test]
    public function it_attributes_no_principal_when_the_secret_does_not_verify(): void
    {
        // Arrange

        $client = ApiClient::factory()->create(['client_secret' => Hash::make('TheRealSecret12')]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->client_id,
            'client_secret' => 'not-the-right-secret',
        ]);

        // Assert

        $response->assertBadRequest();
        $response->assertJsonPath('error', 'invalid_client');

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::ClientTokenExchangeFailed->value,
            'outcome' => AuditOutcome::Failed->value,
            'client_ineligibility_reason' => null,
            'user_id' => null,
            'api_client_id' => null,
        ]);
    }

    #[Test]
    public function it_rejects_inactive_clients(): void
    {
        // Arrange

        $plainSecret = 'InactiveSecret12';
        $client = ApiClient::factory()->inactive()->create([
            'client_secret' => Hash::make($plainSecret),
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->client_id,
            'client_secret' => $plainSecret,
        ]);

        // Assert

        $response->assertBadRequest();
        $response->assertJsonPath('error', 'invalid_client');
        $response->assertJsonPath('error_description', 'Client Authentication Failed');
    }

    /**
     * Reject unsupported grant types and missing credential fields.
     *
     * @param array<string, mixed> $payload      the hostile request body
     * @param string               $expectedCode the RFC 6749 error code the caller must receive
     */
    #[Test]
    #[DataProvider('invalidPayloadProvider')]
    public function it_rejects_invalid_exchange_payloads(array $payload, string $expectedCode): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/oauth/token', $payload);

        // Assert

        $response->assertBadRequest();
        $response->assertJsonPath('error', $expectedCode);
    }

    /**
     * Name the parameter the validator actually rejected.
     *
     * A hardcoded fallback misreports the ordinary case: an oversized `client_secret` alongside a
     * valid `client_id` was described as `Missing Or Invalid Parameter: client_id`, sending the
     * caller to fix the one field it had already got right. Pinned in both directions so the
     * description cannot drift back to whichever field happens to appear first in the rules.
     *
     * @param array<string, mixed> $payload            the request body to reject
     * @param string               $expectedCode       the RFC 6749 error code the caller must receive
     * @param string               $expectedParameter  the field the description must name
     * @param string               $forbiddenParameter the field the description must not blame
     */
    #[Test]
    #[DataProvider('mislabelledParameterProvider')]
    public function it_names_the_parameter_that_actually_failed(
        array $payload,
        string $expectedCode,
        string $expectedParameter,
        string $forbiddenParameter,
    ): void {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/oauth/token', $payload);

        // Assert

        $response->assertBadRequest();
        $response->assertJsonPath('error', $expectedCode);

        $description = $response->json('error_description');

        $this->assertIsString($description);

        $this->assertStringContainsString($expectedParameter, $description);
        $this->assertStringNotContainsString(
            $forbiddenParameter,
            $description,
            'The description must not blame a field that passed validation.',
        );
    }

    /**
     * A valid client_id paired with an oversized client_secret, and the mirror image.
     *
     * The third element is the field that must NOT be named, so each row also proves the response
     * does not simply blame whichever credential field happens to come first in the rules.
     *
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string, 3: string}>
     */
    public static function mislabelledParameterProvider(): array
    {
        return [
            'oversized client secret with a valid client id' => [
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => 'demo-integration-client',
                    'client_secret' => str_repeat('x', 256),
                ],
                'invalid_request',
                'client_secret',
                'client_id',
            ],
            'oversized client id' => [
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => str_repeat('y', 256),
                    'client_secret' => 'valid-secret',
                ],
                'invalid_request',
                'client_id',
                'client_secret',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Invalid exchange payloads mapped to the validation key that must error.
     *
     * @return array<string, array{0: array<string, mixed>, 1: string}> case name mapped to [payload, expectedErrorKey]
     */
    public static function invalidPayloadProvider(): array
    {
        return [
            'unsupported grant type' => [
                [
                    'grant_type' => 'password',
                    'client_id' => 'demo',
                    'client_secret' => 'secret',
                ],
                'unsupported_grant_type',
            ],
            'missing grant type' => [
                [
                    'client_id' => 'demo',
                    'client_secret' => 'secret',
                ],
                'invalid_request',
            ],
            'missing client id' => [
                [
                    'grant_type' => 'client_credentials',
                    'client_secret' => 'secret',
                ],
                'invalid_request',
            ],
            'missing client secret' => [
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => 'demo',
                ],
                'invalid_request',
            ],
        ];
    }
}
