<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\User;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for API authentication error responses.
 */
#[CoversClass(ApiResponse::class)]
final class ApiAuthenticationTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return the standard envelope when no bearer token is sent.
     */
    #[Test]
    public function it_returns_the_standard_envelope_when_no_bearer_token_is_sent(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/users');

        // Assert

        $response->assertUnauthorized();
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('status_code', 401);
        $response->assertJsonPath('message', 'Unauthenticated');
        $response->assertJsonPath('data', null);
    }

    /**
     * Return the standard envelope when the bearer token is invalid.
     */
    #[Test]
    public function it_returns_the_standard_envelope_when_the_bearer_token_is_invalid(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withToken('not-a-valid-token')->getJson('/api/users');

        // Assert

        $response->assertUnauthorized();
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('status_code', 401);
        $response->assertJsonPath('message', 'Unauthenticated');
        $response->assertJsonPath('data', null);
    }

    /**
     * Return the standard envelope when the caller is forbidden.
     */
    #[Test]
    public function it_returns_the_standard_envelope_when_the_caller_is_forbidden(): void
    {
        // Arrange

        $this->seed(RolesAndPermissionsSeeder::class);

        /** @var User $user */
        $user = User::factory()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->getJson('/api/users');

        // Assert

        $response->assertForbidden();
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('status_code', 403);
        $response->assertJsonPath('message', 'Forbidden');
        $response->assertJsonPath('data', null);
        $response->assertJsonPath('meta', []);
    }

    /**
     * Return the standard envelope when a resource is not found.
     */
    #[Test]
    public function it_returns_the_standard_envelope_when_a_resource_is_not_found(): void
    {
        // Arrange

        $this->seed(RolesAndPermissionsSeeder::class);

        /** @var User $user */
        $user = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($user)->getJson('/api/users/99999');

        // Assert

        $response->assertNotFound();
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('status_code', 404);
        $response->assertJsonPath('message', 'Resource Not Found');
        $response->assertJsonPath('data', null);
        $response->assertJsonPath('meta', []);
    }

    /**
     * Never redirect unauthenticated API requests to a web login route.
     */
    #[Test]
    public function it_never_redirects_unauthenticated_api_requests_to_a_login_route(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->call('GET', '/api/tokens');

        // Assert

        $response->assertUnauthorized();
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('status_code', 401);
        $response->assertJsonPath('message', 'Unauthenticated');
        $this->assertNotSame(302, $response->getStatusCode());
    }

    /**
     * Return the standard envelope for plain curl requests without an Accept header.
     */
    #[Test]
    public function it_returns_the_standard_envelope_for_plain_curl_requests_without_an_accept_header(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->call('GET', '/api/users');

        // Assert

        $response->assertUnauthorized();
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('status_code', 401);
        $response->assertJsonPath('message', 'Unauthenticated');
        $response->assertJsonPath('data', null);
    }
}
