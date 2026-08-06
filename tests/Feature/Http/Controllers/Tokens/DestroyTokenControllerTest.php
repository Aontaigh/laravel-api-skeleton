<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Tokens;

use App\Actions\Tokens\RevokePersonalAccessTokenAction;
use App\Http\Controllers\Tokens\DestroyTokenController;
use App\Http\Requests\Tokens\DestroyTokenRequest;
use App\Models\User;
use App\Policies\PersonalAccessTokenPolicy;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the Token revocation endpoint.
 */
#[CoversClass(DestroyTokenController::class)]
#[CoversClass(DestroyTokenRequest::class)]
#[CoversClass(RevokePersonalAccessTokenAction::class)]
#[CoversClass(PersonalAccessTokenPolicy::class)]
#[CoversClass(ApiResponse::class)]
final class DestroyTokenControllerTest extends TestCase
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

    /**
     * Revoke the viewer's own Token.
     */
    #[Test]
    public function it_revokes_the_viewers_own_token(): void
    {
        // Arrange

        /** @var User $viewer */
        $viewer = User::factory()->user()->create();

        $token = $viewer->createToken('My Token')->accessToken;

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($viewer)->deleteJson("/api/tokens/{$token->id}");

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Token Revoked Successfully');
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    /**
     * Deny revoking another User's Token with not found.
     */
    #[Test]
    public function it_denies_revoking_another_users_token_with_not_found(): void
    {
        /*
         * Route binding is scoped to the caller's own tokens, so a foreign
         * token id returns 404 — not 403 — and cannot be probed for existence.
         */

        // Arrange

        /** @var User $viewer */
        $viewer = User::factory()->user()->create();

        /** @var User $otherUser */
        $otherUser = User::factory()->user()->create();

        $token = $otherUser->createToken('Not Mine')->accessToken;

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($viewer)->deleteJson("/api/tokens/{$token->id}");

        // Assert

        $response->assertNotFound();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->id]);
    }

    /**
     * Deny viewers without the revoke-own permission.
     */
    #[Test]
    public function it_denies_viewers_without_the_revoke_own_permission(): void
    {
        // Arrange

        /** @var User $roleless */
        $roleless = User::factory()->create();

        $token = $roleless->createToken('Own Token')->accessToken;

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($roleless)->deleteJson("/api/tokens/{$token->id}");

        // Assert

        $response->assertForbidden();
    }

    /**
     * Return not found for a nonexistent Token.
     */
    #[Test]
    public function it_returns_not_found_for_a_nonexistent_token(): void
    {
        // Arrange

        /** @var User $viewer */
        $viewer = User::factory()->user()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($viewer)->deleteJson('/api/tokens/999999');

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

        /** @var User $someUser */
        $someUser = User::factory()->user()->create();

        $token = $someUser->createToken('Some Token')->accessToken;

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->deleteJson("/api/tokens/{$token->id}");

        // Assert

        $response->assertUnauthorized();
    }
}
