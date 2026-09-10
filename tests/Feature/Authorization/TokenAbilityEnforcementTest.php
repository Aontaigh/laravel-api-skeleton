<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Actions\Auth\AuthenticateClientCredentialsAction;
use App\Actions\Auth\ExchangeClientCredentialsAction;
use App\Actions\Tokens\CreatePersonalAccessTokenAction;
use App\DataTransferObjects\Auth\ClientCredentialsData;
use App\DataTransferObjects\Tokens\CreateTokenData;
use App\Http\Controllers\Auth\ClientTokenExchangeController;
use App\Http\Controllers\Tokens\TokenIndexController;
use App\Http\Controllers\Users\StoreUserTokenController;
use App\Http\Controllers\Users\UserIndexController;
use App\Http\Requests\Auth\ClientTokenExchangeRequest;
use App\Http\Requests\Tokens\TokenIndexRequest;
use App\Http\Requests\Users\StoreUserTokenRequest;
use App\Http\Requests\Users\UserIndexRequest;
use App\Models\ApiClient;
use App\Models\User;
use App\Policies\PersonalAccessTokenPolicy;
use App\Policies\UserPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for Sanctum Token ability enforcement on `User::can()`.
 *
 * Before the `User::can()` override, Token abilities were validated at
 * issuance but never consulted afterwards: a Token scoped to `roles.list`
 * still inherited every Spatie permission of its Service role.
 */
#[CoversClass(User::class)]
#[CoversClass(UserPolicy::class)]
#[CoversClass(UserIndexRequest::class)]
#[CoversClass(UserIndexController::class)]
#[CoversClass(StoreUserTokenController::class)]
#[CoversClass(StoreUserTokenRequest::class)]
#[CoversClass(TokenIndexRequest::class)]
#[CoversClass(TokenIndexController::class)]
#[CoversClass(PersonalAccessTokenPolicy::class)]
#[CoversClass(CreatePersonalAccessTokenAction::class)]
#[CoversClass(CreateTokenData::class)]
#[CoversClass(ClientTokenExchangeController::class)]
#[CoversClass(ClientTokenExchangeRequest::class)]
#[CoversClass(ExchangeClientCredentialsAction::class)]
#[CoversClass(AuthenticateClientCredentialsAction::class)]
#[CoversClass(ClientCredentialsData::class)]
final class TokenAbilityEnforcementTest extends TestCase
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
     * Seed the roles and permissions every test authorises against.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Exchange client credentials for a scoped bearer token through the
     * client-credentials endpoint.
     *
     * @param  list<string> $abilities the abilities stamped onto the issued Token
     * @return string       the plaintext bearer Token
     */
    private function exchangeClientToken(array $abilities): string
    {
        $plainSecret = 'ClientSecretValue12';

        $client = ApiClient::factory()->create([
            'client_secret' => Hash::make($plainSecret),
            'abilities' => $abilities,
        ]);

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->client_id,
            'client_secret' => $plainSecret,
        ]);

        $response->assertOk();

        /** @var string $plainTextToken */
        $plainTextToken = $response->json('data.plain_text_token');

        return $plainTextToken;
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /*
     * Client Credentials Tests
     * ------------------------
     */

    /**
     * Deny a client-credentials Token whose abilities do not cover the
     * permission the endpoint checks.
     *
     * The Service role holds `users.list`, but the client was issued with
     * only `roles.list`: the Token scope, not the role, must decide.
     */
    #[Test]
    public function it_denies_a_client_token_whose_abilities_do_not_cover_the_endpoint(): void
    {
        // Arrange

        $plainTextToken = $this->exchangeClientToken(['roles.list']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withToken($plainTextToken)->getJson('/api/users');

        // Assert

        $response->assertForbidden();
    }

    /**
     * Allow a client-credentials Token whose abilities cover every permission
     * the endpoint checks.
     */
    #[Test]
    public function it_allows_a_client_token_whose_abilities_cover_the_endpoint(): void
    {
        // Arrange

        $plainTextToken = $this->exchangeClientToken(['users.list', 'users.view-email']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withToken($plainTextToken)->getJson('/api/users');

        // Assert

        $response->assertOk();
    }

    /*
     * Wildcard Token Tests
     * --------------------
     */

    /**
     * Keep self-service Tokens with the default `*` abilities unrestricted.
     */
    #[Test]
    public function it_keeps_a_wildcard_self_service_token_unrestricted(): void
    {
        // Arrange

        /** @var User $viewer */
        $viewer = User::factory()->user()->create();

        /** @var TestResponse<JsonResponse> $createResponse */
        $createResponse = $this->actingAs($viewer)->postJson('/api/tokens', ['name' => 'CLI Token']);

        $createResponse->assertCreated();

        /** @var string $plainTextToken */
        $plainTextToken = $createResponse->json('data.plain_text_token');

        /*
         * Sanctum resolves the session User ahead of the bearer Token, so the
         * in-memory `actingAs` authentication must be dropped before the
         * Token-backed request genuinely exercises the Token path.
         */
        Auth::forgetGuards();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withToken($plainTextToken)->getJson('/api/tokens');

        // Assert

        $response->assertOk();
    }

    /*
     * Admin-Issued Token Tests
     * ------------------------
     */

    /**
     * Allow an admin-issued Token scoped to `users.list` on the user index,
     * then deny it on user creation.
     *
     * The Admin role holds `users.create`, so the `403` is attributable to
     * the Token scope alone.
     */
    #[Test]
    public function it_scopes_an_admin_issued_token_to_its_declared_abilities(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $target */
        $target = User::factory()->admin()->create();

        /** @var TestResponse<JsonResponse> $createResponse */
        $createResponse = $this->actingAs($admin)->postJson("/api/users/{$target->id}/tokens", [
            'name' => 'Scoped Token',
            'abilities' => ['users.list'],
        ]);

        $createResponse->assertCreated();

        /** @var string $plainTextToken */
        $plainTextToken = $createResponse->json('data.plain_text_token');

        /*
         * Sanctum resolves the session User ahead of the bearer Token, so the
         * in-memory `actingAs` authentication must be dropped before the
         * Token-backed requests genuinely exercise the Token path.
         */
        Auth::forgetGuards();

        // Act

        /** @var TestResponse<JsonResponse> $indexResponse */
        $indexResponse = $this->withToken($plainTextToken)->getJson('/api/users');

        /** @var TestResponse<JsonResponse> $storeResponse */
        $storeResponse = $this->withToken($plainTextToken)->postJson('/api/users', [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'password' => 'SecretPassword12',
        ]);

        // Assert

        $indexResponse->assertOk();
        $storeResponse->assertForbidden();
    }

    /*
     * Session Authentication Tests
     * ----------------------------
     */

    /**
     * Leave session-cookie authentication unaffected by Token scoping.
     */
    #[Test]
    public function it_leaves_session_cookie_authentication_unaffected(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($manager)->getJson('/api/users');

        // Assert

        $response->assertOk();
    }
}
