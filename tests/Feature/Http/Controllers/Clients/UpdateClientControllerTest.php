<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Clients;

use App\Actions\ApiClients\UpdateApiClientAction;
use App\DataTransferObjects\ApiClients\UpdateApiClientData;
use App\Http\Controllers\Clients\UpdateClientController;
use App\Http\Requests\ApiClients\UpdateClientRequest;
use App\Http\Resources\ApiClientResource;
use App\Models\ApiClient;
use App\Models\User;
use App\Policies\ApiClientPolicy;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for API client update.
 */
#[CoversClass(UpdateClientController::class)]
#[CoversClass(UpdateClientRequest::class)]
#[CoversClass(UpdateApiClientAction::class)]
#[CoversClass(UpdateApiClientData::class)]
#[CoversClass(ApiClientResource::class)]
#[CoversClass(ApiClientPolicy::class)]
#[CoversClass(ApiResponse::class)]
final class UpdateClientControllerTest extends TestCase
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
     * Seed permissions for API client management.
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
     * Mutation Tests
     * --------------
     */

    /**
     * Update the name and abilities of an API client.
     */
    #[Test]
    public function it_updates_the_name_and_abilities_of_an_api_client(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $client = ApiClient::factory()->create([
            'name' => 'Old Name',
            'abilities' => ['users.list'],
        ]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson('/api/clients/'.$client->id, [
            'name' => 'Billing Sync',
            'abilities' => ['users.list', 'users.list-all'],
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'API Client Updated Successfully');
        $response->assertJsonPath('data.name', 'Billing Sync');
        $response->assertJsonPath('data.abilities', ['users.list', 'users.list-all']);

        $this->assertDatabaseHas('api_clients', [
            'id' => $client->id,
            'name' => 'Billing Sync',
        ]);

        $updated = ApiClient::query()->findOrFail($client->id);
        $this->assertSame(['users.list', 'users.list-all'], $updated->abilities);

        $this->assertDatabaseHas('users', [
            'id' => $client->user_id,
            'name' => 'Billing Sync',
        ]);
    }

    /**
     * Deactivate a client via is_active: false.
     */
    #[Test]
    public function it_deactivates_a_client(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $client = ApiClient::factory()->create(['is_active' => true]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson('/api/clients/'.$client->id, [
            'is_active' => false,
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'API Client Updated Successfully');
        $response->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('api_clients', [
            'id' => $client->id,
            'is_active' => false,
        ]);
    }

    /**
     * Kill every outstanding bearer token when a client is deactivated.
     *
     * Regression: `is_active` is only consulted at exchange time, so without
     * the revocation a compromised integration's already-issued token kept
     * working until its natural expiry.
     */
    #[Test]
    public function it_kills_outstanding_tokens_when_a_client_is_deactivated(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $client = ApiClient::factory()->create(['is_active' => true]);
        $token = $client->user->createToken('service-token', $client->abilities);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson('/api/clients/'.$client->id, [
            'is_active' => false,
        ]);

        // Assert

        $response->assertOk();

        Auth::forgetGuards();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/users')
            ->assertUnauthorized();
    }

    /**
     * Keep outstanding bearer tokens when the abilities are re-submitted in a
     * different order with the same values.
     *
     * Ability lists are unordered sets: an update from
     * `['users.list', 'users.list-all']` to `['users.list-all', 'users.list']`
     * grants nothing new and removes nothing, so revoking every live token -
     * which forces every integration to re-exchange - would be an outage
     * caused by array key order.
     */
    #[Test]
    public function it_keeps_tokens_when_abilities_are_reordered_without_changing(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $client = ApiClient::factory()->create([
            'is_active' => true,
            'abilities' => ['users.list', 'users.list-all'],
        ]);
        $token = $client->user->createToken('service-token', $client->abilities);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson('/api/clients/'.$client->id, [
            'abilities' => ['users.list-all', 'users.list'],
        ]);

        // Assert

        $response->assertOk();
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    /**
     * Kill every outstanding bearer token when a client's abilities are
     * broadened, so the new grant takes effect immediately.
     *
     * Exchange copies the client's abilities onto the token at mint time -
     * an issued token never gains abilities, so without revocation the
     * broadened grant would sit inert for up to the configured expiry while
     * the token keeps its stale, narrower list. Revoking forces the
     * integration to re-exchange under the new scope: the same contract the
     * narrowing path enforces, for the mirrored reason.
     */
    #[Test]
    public function it_kills_outstanding_tokens_when_a_client_is_broadened(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $client = ApiClient::factory()->create([
            'is_active' => true,
            'abilities' => ['users.list'],
        ]);
        $token = $client->user->createToken('service-token', $client->abilities);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson('/api/clients/'.$client->id, [
            'abilities' => ['users.list', 'users.list-all'],
        ]);

        // Assert

        $response->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);

        /*
         * The re-exchange mints a token carrying the broadened grant
         * immediately - the whole point of revoking on broadening.
         */
        $exchange = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->client_id,
            'client_secret' => \Database\Factories\ApiClientFactory::plainTextSecret(),
        ]);

        $exchange->assertOk();
        $this->assertSame(
            ['users.list', 'users.list-all'],
            $exchange->json('data.token.abilities'),
        );
    }

    /**
     * Kill every outstanding bearer token when a client's abilities are narrowed.
     *
     * Regression: tokens carry the abilities they were minted with, so
     * narrowing the grant without revocation let an already-issued token
     * keep exercising powers that had just been removed.
     */
    #[Test]
    public function it_kills_outstanding_tokens_when_a_client_is_narrowed(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $client = ApiClient::factory()->create([
            'is_active' => true,
            'abilities' => ['users.list', 'users.list-all'],
        ]);
        $token = $client->user->createToken('service-token', $client->abilities);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson('/api/clients/'.$client->id, [
            'abilities' => ['users.list'],
        ]);

        // Assert

        $response->assertOk();

        Auth::forgetGuards();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/users')
            ->assertUnauthorized();
    }

    /**
     * Not resurrect dead tokens when a deactivated client is reactivated.
     *
     * Re-enabling must require a fresh token exchange against the client
     * secret; deleting the tokens on deactivation is not undone.
     */
    #[Test]
    public function it_does_not_resurrect_tokens_when_a_client_is_reactivated(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $client = ApiClient::factory()->create(['is_active' => true]);
        $token = $client->user->createToken('service-token', $client->abilities);

        $this->actingAs($admin)->patchJson('/api/clients/'.$client->id, [
            'is_active' => false,
        ])->assertOk();

        // Act

        $this->actingAs($admin)->patchJson('/api/clients/'.$client->id, [
            'is_active' => true,
        ])->assertOk();

        // Assert

        Auth::forgetGuards();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/users')
            ->assertUnauthorized();
    }

    /**
     * Reject the wildcard ability for an API client.
     *
     * A machine identity must be scoped: the unrestricted wildcard would hand
     * every current and future permission to one non-interactive caller.
     */
    #[Test]
    public function it_rejects_the_wildcard_ability(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $client = ApiClient::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson('/api/clients/'.$client->id, [
            'abilities' => ['*'],
        ]);

        // Assert

        $response->assertUnprocessable();
        $response->assertJsonPath('meta.invalid_abilities', ['*']);

        $this->assertSame(['users.list'], $client->refresh()->abilities);
    }

    /**
     * Reactivate a deactivated client via is_active: true.
     */
    #[Test]
    public function it_reactivates_a_deactivated_client(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $client = ApiClient::factory()->create(['is_active' => false]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson('/api/clients/'.$client->id, [
            'is_active' => true,
        ]);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('api_clients', [
            'id' => $client->id,
            'is_active' => true,
        ]);
    }

    /*
     * Authentication Tests
     * --------------------
     */

    /**
     * Deny unauthenticated requests.
     */
    #[Test]
    public function it_denies_unauthenticated_requests(): void
    {
        // Arrange

        $client = ApiClient::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->patchJson('/api/clients/'.$client->id, [
            'name' => 'Blocked',
        ]);

        // Assert

        $response->assertUnauthorized();
    }

    /*
     * Authorisation Tests
     * -------------------
     */

    /**
     * Deny non-admin callers.
     */
    #[Test]
    public function it_forbids_non_admin_callers(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();
        $client = ApiClient::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($manager)->patchJson('/api/clients/'.$client->id, [
            'name' => 'Blocked',
        ]);

        // Assert

        $response->assertForbidden();
    }

    /*
     * Not Found Tests
     * ---------------
     */

    /**
     * Return not found for an unknown client ID.
     */
    #[Test]
    public function it_returns_not_found_for_an_unknown_client_id(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson('/api/clients/99999', [
            'name' => 'Ghost',
        ]);

        // Assert

        $response->assertNotFound();
    }

    /*
     * Validation Tests
     * ----------------
     */

    /**
     * Reject an empty payload.
     */
    #[Test]
    public function it_rejects_an_empty_payload(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $client = ApiClient::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson('/api/clients/'.$client->id, []);

        // Assert

        $response->assertUnprocessable();
    }

    /**
     * Reject unknown abilities.
     */
    #[Test]
    public function it_rejects_unknown_abilities(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $client = ApiClient::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson('/api/clients/'.$client->id, [
            'abilities' => ['read', 'write'],
        ]);

        // Assert

        $response->assertUnprocessable();
        $response->assertJsonPath('meta.invalid_abilities', ['read', 'write']);
    }

    /**
     * Reject is_active as a non-boolean value.
     */
    #[Test]
    public function it_rejects_is_active_as_a_non_boolean_value(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $client = ApiClient::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->patchJson('/api/clients/'.$client->id, [
            'is_active' => 'yes',
        ]);

        // Assert

        $response->assertUnprocessable();
    }
}
