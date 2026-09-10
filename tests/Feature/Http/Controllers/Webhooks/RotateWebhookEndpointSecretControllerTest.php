<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Webhooks;

use App\Actions\Webhooks\RotateWebhookEndpointSecretAction;
use App\DataTransferObjects\Webhooks\RotatedWebhookSecretResult;
use App\Http\Controllers\Webhooks\RotateWebhookEndpointSecretController;
use App\Http\Requests\Webhooks\RotateWebhookSecretRequest;
use App\Http\Resources\WebhookEndpointResource;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Policies\WebhookEndpointPolicy;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for webhook secret rotation.
 */
#[CoversClass(RotateWebhookEndpointSecretController::class)]
#[CoversClass(RotateWebhookSecretRequest::class)]
#[CoversClass(RotateWebhookEndpointSecretAction::class)]
#[CoversClass(RotatedWebhookSecretResult::class)]
#[CoversClass(WebhookEndpointResource::class)]
#[CoversClass(WebhookEndpointPolicy::class)]
#[CoversClass(ApiResponse::class)]
final class RotateWebhookEndpointSecretControllerTest extends TestCase
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

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        $oldSecret = $endpoint->secret;

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson("/api/webhook-endpoints/{$endpoint->id}/rotate-secret");

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Webhook Secret Rotated Successfully');
        $response->assertJsonMissingPath('data.endpoint.secret');

        /** @var string $newSecret */
        $newSecret = $response->json('data.webhook_secret');

        $this->assertNotSame($oldSecret, $newSecret);
        $this->assertSame($newSecret, $endpoint->refresh()->secret);
    }

    /**
     * Reset the failure streak on rotation without re-enabling the endpoint.
     */
    #[Test]
    public function it_resets_the_streak_without_reenabling(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->disabled()->create(['failure_streak' => 10]);

        // Act

        $this->actingAs($admin)->postJson("/api/webhook-endpoints/{$endpoint->id}/rotate-secret")->assertOk();

        // Assert

        $fresh = $endpoint->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(0, $fresh->failure_streak);
        $this->assertFalse($fresh->is_active);
    }

    /**
     * Return not found for a nonexistent endpoint.
     */
    #[Test]
    public function it_returns_not_found_for_a_nonexistent_endpoint(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/webhook-endpoints/999999/rotate-secret');

        // Assert

        $response->assertNotFound();
    }

    /*
     * Authorization Tests
     * -------------------
     */

    /**
     * Deny Managers.
     */
    #[Test]
    public function it_denies_managers(): void
    {
        // Arrange

        /** @var User $manager */
        $manager = User::factory()->manager()->create();

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($manager)->postJson("/api/webhook-endpoints/{$endpoint->id}/rotate-secret");

        // Assert

        $response->assertForbidden();
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

        /** @var WebhookEndpoint $endpoint */
        $endpoint = WebhookEndpoint::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->postJson("/api/webhook-endpoints/{$endpoint->id}/rotate-secret");

        // Assert

        $response->assertUnauthorized();
    }
}
