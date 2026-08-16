<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Clients;

use App\Actions\ApiClients\RevokeApiClientAction;
use App\Http\Controllers\Clients\DestroyClientController;
use App\Http\Requests\ApiClients\DestroyClientRequest;
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
 * Feature tests for API client revocation.
 */
#[CoversClass(DestroyClientController::class)]
#[CoversClass(DestroyClientRequest::class)]
#[CoversClass(RevokeApiClientAction::class)]
#[CoversClass(ApiClientPolicy::class)]
#[CoversClass(ApiResponse::class)]
final class DestroyClientControllerTest extends TestCase
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
     * Seed permissions for API client management.
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
     * Deactivate the client and revoke service tokens.
     */
    #[Test]
    public function it_deactivates_the_client_and_revokes_service_tokens(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $client = ApiClient::factory()->create();
        $token = $client->user->createToken('service-token', $client->abilities);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->deleteJson('/api/clients/'.$client->id);

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'API Client Revoked Successfully');

        $this->assertDatabaseHas('api_clients', [
            'id' => $client->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        Auth::forgetGuards();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/users')
            ->assertUnauthorized();
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
        $response = $this->deleteJson('/api/clients/'.$client->id);

        // Assert

        $response->assertUnauthorized();
    }

    /*
     * Authorization Tests
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
        $response = $this->actingAs($manager)->deleteJson('/api/clients/'.$client->id);

        // Assert

        $response->assertForbidden();
    }

    /*
     * Not Found Tests
     * ---------------
     */

    /**
     * Return not found for an unknown client id.
     */
    #[Test]
    public function it_returns_not_found_for_an_unknown_client_id(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->deleteJson('/api/clients/99999');

        // Assert

        $response->assertNotFound();
    }
}
