<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Clients;

use App\Actions\ApiClients\RotateApiClientSecretAction;
use App\DataTransferObjects\ApiClients\RotatedClientSecretResult;
use App\Enums\AuthAuditEvent;
use App\Http\Controllers\Clients\RotateClientSecretController;
use App\Http\Requests\Clients\RotateClientSecretRequest;
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
 * Feature tests for API client secret rotation.
 */
#[CoversClass(RotateClientSecretController::class)]
#[CoversClass(RotateClientSecretRequest::class)]
#[CoversClass(RotateApiClientSecretAction::class)]
#[CoversClass(RotatedClientSecretResult::class)]
#[CoversClass(ApiClientResource::class)]
#[CoversClass(ApiClientPolicy::class)]
#[CoversClass(ApiResponse::class)]
final class RotateClientSecretControllerTest extends TestCase
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
     * Seed roles and permissions.
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
     * Rotate the secret, returning the new plaintext exactly once.
     */
    #[Test]
    public function it_rotates_the_secret_and_returns_it_once(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var ApiClient $client */
        $client = ApiClient::factory()->create();

        $oldSecret = 'OriginalSecret12';
        $client->forceFill(['client_secret' => bcrypt($oldSecret)])->save();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson("/api/clients/{$client->id}/rotate-secret");

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Client Secret Rotated Successfully');
        $response->assertJsonMissingPath('data.client.client_secret');

        /** @var string $newSecret */
        $newSecret = $response->json('data.client_secret');

        $this->assertNotSame($oldSecret, $newSecret);
        $this->assertSame(40, strlen($newSecret));
        $this->assertNotSame($newSecret, $client->refresh()->getAttributes()['client_secret']);
    }

    /**
     * Invalidate the old secret for future exchanges while the new one works.
     */
    #[Test]
    public function it_rejects_the_old_secret_and_accepts_the_new_on_exchange(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var ApiClient $client */
        $client = ApiClient::factory()->create();

        $oldSecret = 'OriginalSecret12';
        $client->forceFill(['client_secret' => bcrypt($oldSecret)])->save();

        $rotateResponse = $this->actingAs($admin)->postJson("/api/clients/{$client->id}/rotate-secret");
        $rotateResponse->assertOk();

        /** @var string $newSecret */
        $newSecret = $rotateResponse->json('data.client_secret');

        // Act + Assert

        /*
         * The old secret is dead: exchange with it answers the generic 422.
         */
        $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->client_id,
            'client_secret' => $oldSecret,
        ])->assertUnprocessable();

        // Act + Assert

        /*
         * The new secret exchanges for a fresh bearer token.
         */
        $exchange = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->client_id,
            'client_secret' => $newSecret,
        ]);

        $exchange->assertOk();
        $this->assertNotNull($exchange->json('data.plain_text_token'));
    }

    /**
     * Reset the failure-adjacent state without touching the active flag:
     * rotation must never silently resume a deliberately paused client.
     */
    #[Test]
    public function it_keeps_a_deactivated_client_deactivated(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var ApiClient $client */
        $client = ApiClient::factory()->inactive()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson("/api/clients/{$client->id}/rotate-secret");

        // Assert

        $response->assertOk();
        $this->assertFalse($client->refresh()->is_active);
    }

    /**
     * Record the rotation in the audit trail.
     */
    #[Test]
    public function it_records_a_client_secret_rotated_audit_event(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var ApiClient $client */
        $client = ApiClient::factory()->create();

        // Act

        $this->actingAs($admin)->postJson("/api/clients/{$client->id}/rotate-secret")->assertOk();

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => AuthAuditEvent::ClientSecretRotated->value,
            'user_id' => $admin->id,
            'api_client_id' => $client->id,
        ]);
    }

    /**
     * Return not found for a nonexistent client.
     */
    #[Test]
    public function it_returns_not_found_for_a_nonexistent_client(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/clients/999999/rotate-secret');

        // Assert

        $response->assertNotFound();
    }

    /*
     * Authentication Tests
     * --------------------
     */

    /**
     * Deny unauthenticated callers.
     */
    #[Test]
    public function it_denies_unauthenticated_callers(): void
    {
        // Arrange

        /** @var ApiClient $client */
        $client = ApiClient::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson("/api/clients/{$client->id}/rotate-secret");

        // Assert

        $response->assertUnauthorized();
    }

    /*
     * Authorization Tests
     * -------------------
     */

    /**
     * Deny Managers, Users, and service accounts.
     */
    #[Test]
    public function it_denies_callers_without_api_clients_update(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();

        /** @var User $user */
        $user = User::factory()->user()->create();

        /** @var User $serviceUser */
        $serviceUser = User::factory()->serviceAccount()->service()->create();

        /** @var ApiClient $client */
        $client = ApiClient::factory()->create();

        // Act + Assert

        $this->actingAs($manager)->postJson("/api/clients/{$client->id}/rotate-secret")->assertForbidden();
        $this->actingAs($user)->postJson("/api/clients/{$client->id}/rotate-secret")->assertForbidden();
        $this->actingAs($serviceUser)->postJson("/api/clients/{$client->id}/rotate-secret")->assertForbidden();
    }
}
