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
                'auth' => ['token_expiration_days'],
                'drivers' => ['cache', 'session', 'queue', 'database', 'mail', 'filesystem'],
            ],
        ]);
    }

    /**
     * Report the configured Token lifetime so clients can mirror it.
     */
    #[Test]
    public function it_reports_the_configured_token_lifetime(): void
    {
        // Arrange

        config(['api.token_expiration_days' => 45]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/app-info');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.auth.token_expiration_days', 45);
    }

    /**
     * Echo the raw configured lifetime unchanged, even at zero.
     *
     * The value is the operator's configuration as written; issuance resolves a
     * zero to the maximum lifetime rather than honouring it as "never expires".
     */
    #[Test]
    public function it_reports_a_zero_lifetime_unchanged(): void
    {
        // Arrange

        config(['api.token_expiration_days' => 0]);

        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/app-info');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.auth.token_expiration_days', 0);
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

        /*
         * `token_expiration_days` is a public lifetime figure, not a
         * credential, so the guard names credential-bearing markers instead
         * of the word "token".
         */
        $this->assertStringNotContainsStringIgnoringCase('secret', $body);
        $this->assertStringNotContainsStringIgnoringCase('password', $body);
        $this->assertStringNotContainsStringIgnoringCase('authorization', $body);
    }
}
