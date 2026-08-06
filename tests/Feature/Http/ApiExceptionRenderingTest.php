<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\User;
use App\Support\ApiExceptionRenderer;
use App\Support\ApiResponse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\AssertsApiEnvelope;
use Tests\TestCase;

/**
 * Feature tests ensuring every API-route error uses the ApiResponse envelope.
 */
#[CoversClass(ApiExceptionRenderer::class)]
#[CoversClass(ApiResponse::class)]
final class ApiExceptionRenderingTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AssertsApiEnvelope;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Seed permissions and register a throw route for server-error coverage.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Route::middleware(['auth:sanctum', 'throttle:api'])->get(
            '/api/__test/server-error',
            static fn (): never => throw new RuntimeException('Test Failure'),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return the standard envelope for an unsupported HTTP method.
     */
    #[Test]
    public function it_returns_the_standard_envelope_for_an_unsupported_http_method(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->postJson('/api/roles/1');

        // Assert

        $this->assertApiErrorEnvelope($response, 405, 'Method Not Allowed');
    }

    /**
     * Return the standard envelope for an unexpected server error.
     */
    #[Test]
    public function it_returns_the_standard_envelope_for_an_unexpected_server_error(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/__test/server-error');

        // Assert

        $this->assertApiErrorEnvelope($response, 500, 'Server Error');
        $response->assertJsonMissingPath('exception');
        $response->assertJsonMissingPath('trace');
    }

    /**
     * Return the standard envelope for an unknown API route.
     */
    #[Test]
    public function it_returns_the_standard_envelope_for_an_unknown_api_route(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/not-a-real-route');

        // Assert

        $this->assertApiErrorEnvelope($response, 404, 'Resource Not Found');
    }
}
