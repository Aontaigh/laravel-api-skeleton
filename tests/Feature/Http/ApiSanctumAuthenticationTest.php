<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\User;
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
 * Feature tests for real Sanctum Bearer-Token authentication flows.
 */
#[CoversClass(ApiResponse::class)]
final class ApiSanctumAuthenticationTest extends TestCase
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
     * Seed permissions for token ability checks.
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

    #[Test]
    public function it_authenticates_a_request_with_a_real_personal_access_token(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $plainTextToken = $admin->createToken('feature-test', ['*'])->plainTextToken;

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withToken($plainTextToken)->getJson('/api/users');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
    }

    #[Test]
    public function it_rejects_requests_after_a_token_has_been_revoked(): void
    {
        // Arrange

        /** @var User $viewer */
        $viewer = User::factory()->user()->create();
        $tokenResult = $viewer->createToken('revoke-me', ['tokens.list-own']);
        $plainTextToken = $tokenResult->plainTextToken;
        $tokenId = $tokenResult->accessToken->id;

        $this->withToken($plainTextToken)->getJson('/api/tokens')->assertOk();

        $viewer->tokens()->whereKey($tokenId)->delete();

        Auth::forgetGuards();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withToken($plainTextToken)->getJson('/api/tokens');

        // Assert

        $response->assertUnauthorized();
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('status_code', 401);
        $response->assertJsonPath('message', 'Unauthenticated');
    }

    #[Test]
    public function it_denies_endpoints_outside_a_scoped_tokens_abilities(): void
    {
        // Arrange

        /** @var User $viewer */
        $viewer = User::factory()->user()->create();
        $plainTextToken = $viewer->createToken('list-only', ['tokens.list-own'])->plainTextToken;

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withToken($plainTextToken)->getJson('/api/users');

        // Assert

        $response->assertForbidden();
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('status_code', 403);
        $response->assertJsonPath('message', 'Forbidden');
    }

    #[Test]
    public function it_does_not_treat_a_wildcard_token_as_a_spatie_permission_bypass(): void
    {
        // Arrange

        /** @var User $viewer */
        $viewer = User::factory()->user()->create();
        $plainTextToken = $viewer->createToken('wildcard', ['*'])->plainTextToken;

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withToken($plainTextToken)->getJson('/api/users');

        // Assert

        $response->assertForbidden();
    }
}
