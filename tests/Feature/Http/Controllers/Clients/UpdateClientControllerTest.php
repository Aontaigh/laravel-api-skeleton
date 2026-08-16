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
