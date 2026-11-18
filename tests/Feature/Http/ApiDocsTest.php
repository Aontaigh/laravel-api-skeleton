<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Http\Controllers\Api\ShowApiDocsController;
use App\Http\Controllers\Api\ShowOpenApiSpecController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the hosted Scalar API documentation routes.
 *
 * Both documents describe the whole API surface, so they sit behind the
 * `auth:sanctum` guard: a guest receives the 401 envelope, and a session or
 * bearer token is served the page.
 */
#[CoversClass(ShowApiDocsController::class)]
#[CoversClass(ShowOpenApiSpecController::class)]
final class ApiDocsTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * A guest receives the standard authentication envelope for the docs page.
     */
    #[Test]
    public function it_rejects_a_guest_requesting_the_docs_page(): void
    {
        // Act

        $response = $this->get('/api/docs');

        // Assert

        $response->assertUnauthorized();
        $response->assertJsonPath('status_code', 401);
        $response->assertJsonPath('message', 'Authentication Required');
    }

    /**
     * A guest receives the standard authentication envelope for the spec file.
     */
    #[Test]
    public function it_rejects_a_guest_requesting_the_openapi_specification(): void
    {
        // Act

        $response = $this->get('/api/openapi.yaml');

        // Assert

        $response->assertUnauthorized();
        $response->assertJsonPath('status_code', 401);
    }

    /**
     * Serve the Scalar docs page to an authenticated principal.
     */
    #[Test]
    public function it_serves_the_scalar_docs_page_to_an_authenticated_user(): void
    {
        // Arrange

        Sanctum::actingAs(User::factory()->create(), ['*']);

        // Act

        $response = $this->get('/api/docs');

        // Assert

        $response->assertOk();
        $response->assertSee('data-url', false);
        $response->assertSee('/api/openapi.yaml', false);
        $response->assertSee('@scalar/api-reference@1.64.1', false);
    }

    /**
     * Serve the OpenAPI specification to an authenticated principal.
     */
    #[Test]
    public function it_serves_the_openapi_specification_to_an_authenticated_user(): void
    {
        // Arrange

        Sanctum::actingAs(User::factory()->create(), ['*']);

        // Act

        $response = $this->get('/api/openapi.yaml');

        // Assert

        $response->assertOk();
        $response->assertHeader('content-type', 'application/yaml; charset=utf-8');

        $spec = $response->streamedContent();

        $this->assertStringContainsString('openapi: 3.1', $spec);
        $this->assertStringContainsString('bearerAuth', $spec);
    }

    /**
     * A machine client holding a bearer token reads the specification too, so
     * tooling can regenerate an SDK from the deployed contract.
     */
    #[Test]
    public function it_serves_the_openapi_specification_to_a_bearer_token_client(): void
    {
        // Arrange

        $user = User::factory()->create();
        $token = $user->createToken('docs-tooling')->plainTextToken;

        // Act

        $response = $this->withHeader('Authorization', 'Bearer '.$token)->get('/api/openapi.yaml');

        // Assert

        $response->assertOk();

        $this->assertStringContainsString('openapi: 3.1', $response->streamedContent());
    }
}
