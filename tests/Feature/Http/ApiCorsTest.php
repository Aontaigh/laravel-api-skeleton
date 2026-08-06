<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for cross-origin API access from browser clients.
 */
final class ApiCorsTest extends TestCase
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
     * Seed permissions and pin CORS to a single dev origin for assertions.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        config()->set('cors.allowed_origins', ['http://localhost:5173']);
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Answer browser preflight requests for API routes.
     */
    #[Test]
    public function it_answers_preflight_options_requests_for_allowed_origins(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])->options('/api/tokens');

        // Assert

        $response->assertNoContent();
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
        $this->assertStringContainsString('GET', (string) $response->headers->get('Access-Control-Allow-Methods'));
    }

    /**
     * Reflect allowed origins on authenticated API responses.
     */
    #[Test]
    public function it_reflects_allowed_origins_on_authenticated_api_responses(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $plainTextToken = $admin->createToken('cors-test', ['*'])->plainTextToken;

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
        ])->withToken($plainTextToken)->getJson('/api/users');

        // Assert

        $response->assertOk();
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    /**
     * Never echo a disallowed browser origin in Access-Control-Allow-Origin.
     */
    #[Test]
    public function it_does_not_echo_disallowed_origins(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->withHeaders([
            'Origin' => 'http://evil.test',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'authorization',
        ])->options('/api/tokens');

        // Assert

        $response->assertNoContent();
        $this->assertNotSame('http://evil.test', $response->headers->get('Access-Control-Allow-Origin'));
    }
}
