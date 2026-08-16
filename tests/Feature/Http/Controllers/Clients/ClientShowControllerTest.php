<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Clients;

use App\Http\Controllers\Clients\ClientShowController;
use App\Http\Requests\ApiClients\ClientShowRequest;
use App\Http\Resources\ApiClientResource;
use App\Models\ApiClient;
use App\Models\User;
use App\Policies\ApiClientPolicy;
use App\Queries\ApiClients\ApiClientQueryConstraints;
use App\Queries\IndexFieldsQuery;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the API client show endpoint.
 */
#[CoversClass(ClientShowController::class)]
#[CoversClass(ClientShowRequest::class)]
#[CoversClass(ApiClientResource::class)]
#[CoversClass(ApiClientPolicy::class)]
#[CoversClass(ApiClientQueryConstraints::class)]
#[CoversClass(IndexFieldsQuery::class)]
#[CoversClass(ApiResponse::class)]
final class ClientShowControllerTest extends TestCase
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
     * Seed permissions and enable strict model checks.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading();
        Model::preventAccessingMissingAttributes();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Restore the global strict-mode flags so they do not leak into other suites.
     */
    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        Model::preventAccessingMissingAttributes(false);

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /*
     * Show Tests
     * ----------
     */

    /**
     * Return an API client by id.
     */
    #[Test]
    public function it_returns_an_api_client_by_id(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $client = ApiClient::factory()->create(['name' => 'Billing Sync']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson("/api/clients/{$client->id}");

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'API Client Retrieved Successfully');
        $response->assertJsonPath('data.id', $client->id);
        $response->assertJsonPath('data.name', 'Billing Sync');
    }

    /**
     * Apply sparse fieldsets on the API client.
     */
    #[Test]
    public function it_applies_sparse_fieldsets_on_the_api_client(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $client = ApiClient::factory()->create(['name' => 'Sparse Client']);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            "/api/clients/{$client->id}?fields[api_clients]=id,name",
        );

        // Assert

        $response->assertOk();

        /** @var array<string, mixed> $payload */
        $payload = $response->json('data');

        $this->assertSame(['id', 'name'], array_keys($payload));
    }

    /*
     * Validation and Authorisation Tests
     * ----------------------------------
     */

    /**
     * Reject an unsupported include.
     */
    #[Test]
    public function it_rejects_an_unsupported_include(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $client = ApiClient::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson(
            "/api/clients/{$client->id}?include=user",
        );

        // Assert

        $response->assertUnprocessable();
        $this->assertApiValidationErrors($response, ['include']);
    }

    /**
     * Deny managers without API client list permission.
     */
    #[Test]
    public function it_denies_managers_without_api_client_list_permission(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();
        $client = ApiClient::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($manager)->getJson("/api/clients/{$client->id}");

        // Assert

        $response->assertForbidden();
    }

    /**
     * Return not found for a nonexistent API client.
     */
    #[Test]
    public function it_returns_not_found_for_a_nonexistent_api_client(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/clients/999999');

        // Assert

        $response->assertNotFound();
    }

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
        $response = $this->getJson("/api/clients/{$client->id}");

        // Assert

        $response->assertUnauthorized();
    }
}
