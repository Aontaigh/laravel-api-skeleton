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
use App\Enums\AuthAuditEvent;
use App\Http\Controllers\Auth\ClientTokenExchangeController;
use App\Http\Requests\Auth\ClientTokenExchangeRequest;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Models\ApiClient;
use App\Support\ApiResponse;
use Database\Seeders\ApiClientsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
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
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Seed permissions for token issuance on service accounts.
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

        $response->assertUnprocessable();
        $response->assertJsonPath('meta.errors.client_id', ['Invalid Credentials']);

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

        $response->assertUnprocessable();
        $response->assertJsonPath('meta.errors.client_id', ['Invalid Credentials']);
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
        /* Arrange */

        $plainSecret = 'SoftDeletedSecret12';
        $client = ApiClient::factory()->create([
            'client_secret' => Hash::make($plainSecret),
        ]);
        $client->user->delete();

        /* Act */

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->client_id,
            'client_secret' => $plainSecret,
        ]);

        /* Assert */

        $response->assertUnprocessable();
        $response->assertJsonPath('meta.errors.client_id', ['Invalid Credentials']);
    }

    /**
     * Reject inactive clients with the same generic message as a wrong secret.
     */
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

        $response->assertUnprocessable();
        $response->assertJsonPath('meta.errors.client_id', ['Invalid Credentials']);
    }

    /**
     * Reject unsupported grant types and missing credential fields.
     *
     * @param array<string, mixed> $payload          the hostile request body
     * @param string               $expectedErrorKey the validation key that must error
     */
    #[Test]
    #[DataProvider('invalidPayloadProvider')]
    public function it_rejects_invalid_exchange_payloads(array $payload, string $expectedErrorKey): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/oauth/token', $payload);

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, [$expectedErrorKey]);
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
                'grant_type',
            ],
            'missing grant type' => [
                [
                    'client_id' => 'demo',
                    'client_secret' => 'secret',
                ],
                'grant_type',
            ],
            'missing client id' => [
                [
                    'grant_type' => 'client_credentials',
                    'client_secret' => 'secret',
                ],
                'client_id',
            ],
            'missing client secret' => [
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => 'demo',
                ],
                'client_secret',
            ],
        ];
    }
}
