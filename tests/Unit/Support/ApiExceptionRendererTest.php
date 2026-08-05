<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ApiExceptionRenderer;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for API exception routing decisions.
 */
#[CoversClass(ApiExceptionRenderer::class)]
final class ApiExceptionRendererTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    #[DataProvider('apiRequestProvider')]
    public function it_identifies_which_paths_receive_api_json_envelopes(
        string $path,
        bool $expectsApiEnvelope,
    ): void {
        // Arrange

        $request = Request::create($path, 'GET');

        // Act

        $isApiRequest = ApiExceptionRenderer::isApiRequest($request);

        // Assert

        $this->assertSame($expectsApiEnvelope, $isApiRequest);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Request paths mapped to whether they use the API error envelope.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function apiRequestProvider(): array
    {
        return [
            'users index' => ['/api/users', true],
            'nested resource' => ['/api/users/1', true],
            'scalar docs' => ['/api/docs', false],
            'openapi spec' => ['/api/openapi.yaml', false],
            'health check' => ['/up', false],
            'web root' => ['/', false],
        ];
    }
}
