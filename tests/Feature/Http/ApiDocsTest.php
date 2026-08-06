<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Http\Controllers\Api\ShowApiDocsController;
use App\Http\Controllers\Api\ShowOpenApiSpecController;
use App\Http\Middleware\EnsureCanViewApiDocs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for hosted Scalar API documentation routes.
 */
#[CoversClass(ShowApiDocsController::class)]
#[CoversClass(ShowOpenApiSpecController::class)]
#[CoversClass(EnsureCanViewApiDocs::class)]
final class ApiDocsTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Serve the Scalar docs page.
     */
    #[Test]
    public function it_serves_the_scalar_docs_page(): void
    {
        // Act

        $response = $this->get('/api/docs');

        // Assert

        $response->assertOk();
        $response->assertSee('Scalar.createApiReference', false);
        $response->assertSee('/api/openapi.yaml', false);
    }

    /**
     * Serve the OpenAPI specification.
     */
    #[Test]
    public function it_serves_the_openapi_specification(): void
    {
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
     * Require basic auth for docs when credentials are configured.
     */
    #[Test]
    public function it_requires_basic_auth_for_docs_when_credentials_are_configured(): void
    {
        // Arrange

        config()->set('api.docs_basic_auth.user', 'docs');
        config()->set('api.docs_basic_auth.password', 'secret');

        // Act

        $unauthenticated = $this->get('/api/docs');
        $authenticated = $this->withBasicAuth('docs', 'secret')->get('/api/docs');

        // Assert

        $unauthenticated->assertUnauthorized();
        $authenticated->assertOk();
    }

    /**
     * Require basic auth for the OpenAPI spec when credentials are configured.
     */
    #[Test]
    public function it_requires_basic_auth_for_the_openapi_spec_when_credentials_are_configured(): void
    {
        // Arrange

        config()->set('api.docs_basic_auth.user', 'docs');
        config()->set('api.docs_basic_auth.password', 'secret');

        // Act

        $unauthenticated = $this->get('/api/openapi.yaml');
        $authenticated = $this->withBasicAuth('docs', 'secret')->get('/api/openapi.yaml');

        // Assert

        $unauthenticated->assertUnauthorized();
        $authenticated->assertOk();
    }

    /**
     * Reject incorrect basic auth credentials.
     */
    #[Test]
    public function it_rejects_incorrect_basic_auth_credentials(): void
    {
        // Arrange

        config()->set('api.docs_basic_auth.user', 'docs');
        config()->set('api.docs_basic_auth.password', 'secret');

        // Act

        $response = $this->withBasicAuth('docs', 'wrong-password')->get('/api/docs');

        // Assert

        $response->assertUnauthorized();
    }
}
