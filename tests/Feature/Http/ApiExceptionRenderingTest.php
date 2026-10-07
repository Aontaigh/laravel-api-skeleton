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
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
    | Setup / Teardown
    |--------------------------------------------------------------------------
    */

    /**
     * Seed permissions and register a throw route for server-error coverage.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Route::middleware(['auth:sanctum', 'throttle:api'])->get(
            '/api/__test/server-error',
            static fn (): never => throw new RuntimeException('Test Failure'),
        );

        Route::middleware(['auth:sanctum', 'throttle:api'])->get(
            '/api/__test/not-found',
            static fn (): never => throw new NotFoundHttpException,
        );

        Route::middleware(['auth:sanctum', 'throttle:api'])->get(
            '/api/__test/conflict',
            static fn (): never => throw new HttpException(409),
        );

        Route::middleware(['auth:sanctum', 'throttle:api'])->get(
            '/api/__test/unavailable',
            static fn (): never => throw new HttpException(503),
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
     * Distinguish an unmatched URI from a missing resource: a typo'd path
     * answers "Route Not Found" so the caller does not hunt for a record
     * that was never addressed.
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

        $this->assertApiErrorEnvelope($response, 404, 'Route Not Found');
    }

    /**
     * Keep "Resource Not Found" for a missing Model on a matched route -
     * the URI is valid, the record is not.
     */
    #[Test]
    public function it_returns_resource_not_found_for_a_missing_model_on_a_matched_route(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/users/99999');

        // Assert

        $this->assertApiErrorEnvelope($response, 404, 'Resource Not Found');
    }

    /**
     * Keep "Resource Not Found" when a matched route aborts with 404 -
     * routing succeeded, so the failure is about the resource, not the path.
     */
    #[Test]
    public function it_returns_resource_not_found_when_a_matched_route_aborts(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/__test/not-found');

        // Assert

        $this->assertApiErrorEnvelope($response, 404, 'Resource Not Found');
    }

    /**
     * Label an unmapped 4xx with its official reason phrase, never a wrong
     * one - a 409 answers "Conflict", not "Bad Request".
     */
    #[Test]
    public function it_labels_an_unmapped_client_error_with_its_reason_phrase(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/__test/conflict');

        // Assert

        $this->assertApiErrorEnvelope($response, 409, 'Conflict');
    }

    /**
     * Keep unmapped 5xx errors generic even when a reason phrase exists -
     * "Bad Gateway" and friends leak infrastructure topology to clients.
     */
    #[Test]
    public function it_keeps_an_unmapped_server_error_generic(): void
    {
        // Arrange

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->actingAs($admin)->getJson('/api/__test/unavailable');

        // Assert

        $this->assertApiErrorEnvelope($response, 503, 'Server Error');
    }
}
