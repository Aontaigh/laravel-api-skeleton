<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\User;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
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

    /**
     * Authenticate a request with a real personal access Token.
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

    /**
     * Reject requests after a Token has been revoked.
     */
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

    /**
     * Deny endpoints outside a scoped Token's abilities.
     */
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

    /**
     * Not treat a wildcard Token as a Spatie permission bypass.
     */
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

    /**
     * Reject requests when a Token has expired.
     */
    #[Test]
    public function it_rejects_requests_when_a_token_has_expired(): void
    {
        // Arrange

        Carbon::setTestNow('2026-01-01 12:00:00');

        /** @var User $viewer */
        $viewer = User::factory()->user()->create();
        $plainTextToken = $viewer->createToken(
            'expires-soon',
            ['tokens.list-own'],
            now()->addDays(config()->integer('api.token_expiration_days')),
        )->plainTextToken;

        Carbon::setTestNow('2026-04-02 12:00:00');

        Auth::forgetGuards();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withToken($plainTextToken)->getJson('/api/tokens');

        // Assert

        $response->assertUnauthorized();
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('status_code', 401);
        $response->assertJsonPath('message', 'Unauthenticated');

        Carbon::setTestNow();
    }
}
