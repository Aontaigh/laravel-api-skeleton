<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Clients;

use App\Actions\ApiClients\CreateApiClientAction;
use App\DataTransferObjects\ApiClients\CreateApiClientData;
use App\DataTransferObjects\ApiClients\CreatedApiClientResult;
use App\Http\Controllers\Clients\StoreClientController;
use App\Http\Requests\ApiClients\StoreClientRequest;
use App\Http\Resources\ApiClientResource;
use App\Models\User;
use App\Policies\ApiClientPolicy;
use App\Services\Permissions\PermissionAbilityCatalog;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for admin API client creation.
 */
#[CoversClass(StoreClientController::class)]
#[CoversClass(StoreClientRequest::class)]
#[CoversClass(CreateApiClientAction::class)]
#[CoversClass(CreateApiClientData::class)]
#[CoversClass(CreatedApiClientResult::class)]
#[CoversClass(ApiClientResource::class)]
#[CoversClass(ApiClientPolicy::class)]
#[CoversClass(PermissionAbilityCatalog::class)]
#[CoversClass(ApiResponse::class)]
final class StoreClientControllerTest extends TestCase
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
     * Create a service account and return the plaintext secret once.
     */
    #[Test]
    public function it_creates_a_service_account_and_returns_the_plaintext_secret_once(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/clients', [
            'name' => 'Billing Sync',
            'abilities' => ['users.list', 'users.list-all'],
        ]);

        // Assert

        $response->assertCreated();
        $response->assertJsonPath('message', 'API Client Created Successfully');
        $response->assertJsonPath('data.client.name', 'Billing Sync');
        $response->assertJsonStructure(['data' => ['client', 'client_secret']]);

        $this->assertDatabaseHas('api_clients', [
            'name' => 'Billing Sync',
            'is_active' => true,
        ]);

        $serviceUser = User::query()->where('is_service_account', true)->first();
        $this->assertNotNull($serviceUser);
        $this->assertTrue($serviceUser->hasRole('Service'));
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
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson('/api/clients', [
            'name' => 'Blocked',
            'abilities' => ['users.list'],
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

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($manager)->postJson('/api/clients', [
            'name' => 'Blocked',
            'abilities' => ['users.list'],
        ]);

        // Assert

        $response->assertForbidden();
    }

    /*
     * Validation Tests
     * ----------------
     */

    /**
     * Reject unknown abilities.
     */
    #[Test]
    public function it_rejects_unknown_abilities(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/clients', [
            'name' => 'Bad Abilities',
            'abilities' => ['read'],
        ]);

        // Assert

        $response->assertUnprocessable();
        $response->assertJsonPath('meta.invalid_abilities', ['read']);
    }

    /**
     * Reject missing required payload fields.
     *
     * @param array<string, mixed> $payload          the hostile request body
     * @param string               $expectedErrorKey the validation key that must error
     */
    #[Test]
    #[DataProvider('invalidPayloadProvider')]
    public function it_rejects_invalid_payloads(array $payload, string $expectedErrorKey): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/clients', $payload);

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
     * Invalid create payloads mapped to the validation key that must error.
     *
     * @return array<string, array{0: array<string, mixed>, 1: string}> case name mapped to [payload, expectedErrorKey]
     */
    public static function invalidPayloadProvider(): array
    {
        return [
            'missing name' => [
                ['abilities' => ['users.list']],
                'name',
            ],
            'missing abilities' => [
                ['name' => 'No Abilities'],
                'abilities',
            ],
            'empty abilities' => [
                ['name' => 'Empty Abilities', 'abilities' => []],
                'abilities',
            ],
        ];
    }
}
