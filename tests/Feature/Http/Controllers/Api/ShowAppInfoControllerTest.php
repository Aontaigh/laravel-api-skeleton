<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api;

use App\Http\Controllers\Api\ShowAppInfoController;
use App\Http\Requests\Api\ShowAppInfoRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the public application info endpoint.
 */
#[CoversClass(ShowAppInfoController::class)]
#[CoversClass(ShowAppInfoRequest::class)]
#[CoversClass(ApiResponse::class)]
final class ShowAppInfoControllerTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Return a successful response from the app-info endpoint.
     */
    #[Test]
    public function it_returns_a_successful_response_from_the_app_info_endpoint(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/app-info');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'App Info Retrieved Successfully');
        $response->assertJsonStructure([
            'data' => [
                'app' => ['name', 'environment', 'debug', 'url', 'locale', 'timezone'],
                'php' => ['version', 'sapi'],
                'server' => ['os', 'architecture'],
                'laravel' => ['version'],
                'drivers' => ['cache', 'session', 'queue', 'database', 'mail', 'filesystem'],
            ],
        ]);
    }

    /**
     * Expose metadata, never secrets or keys.
     */
    #[Test]
    public function it_exposes_no_secrets_or_keys(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/app-info');

        // Assert

        $response->assertOk();

        $body = (string) $response->getContent();

        $this->assertStringNotContainsStringIgnoringCase('secret', $body);
        $this->assertStringNotContainsStringIgnoringCase('password', $body);
        $this->assertStringNotContainsStringIgnoringCase('token', $body);
    }
}
