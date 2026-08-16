<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api;

use App\Http\Controllers\Api\ShowHealthController;
use App\Support\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the health-check endpoint.
 */
#[CoversClass(ShowHealthController::class)]
#[CoversClass(ApiResponse::class)]
final class ShowHealthControllerTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /*
     * Health Tests
     * ------------
     */

    /**
     * Report a reachable database and the application version without auth.
     */
    #[Test]
    public function it_reports_database_reachability_and_version_without_auth(): void
    {
        // Act

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/health');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'Service Healthy');
        $response->assertJsonPath('data.database', 'up');
        $response->assertJsonPath('data.version', config('app.version'));
    }
}
