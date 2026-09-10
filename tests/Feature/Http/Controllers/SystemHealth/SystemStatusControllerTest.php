<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\SystemHealth;

use App\Enums\SystemHealthStatus;
use App\Http\Controllers\SystemHealth\SystemStatusController;
use App\Http\Requests\SystemHealth\ShowSystemStatusRequest;
use App\Models\SystemHealthCheck;
use App\Queries\SystemHealth\SystemHealthHistoryQuery;
use App\Services\SystemHealth\SystemHealthCheckRegistry;
use App\Support\ApiDateTime;
use App\Support\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the public System Status endpoint.
 */
#[CoversClass(SystemStatusController::class)]
#[CoversClass(ShowSystemStatusRequest::class)]
#[CoversClass(SystemHealthHistoryQuery::class)]
#[CoversClass(SystemHealthCheckRegistry::class)]
#[CoversClass(SystemHealthCheck::class)]
#[CoversClass(SystemHealthStatus::class)]
#[CoversClass(ApiResponse::class)]
#[CoversClass(ApiDateTime::class)]
final class SystemStatusControllerTest extends TestCase
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
     * Extract the `components[]` payload from a status response.
     *
     * @param  TestResponse<JsonResponse> $response the status response
     * @return list<array<string, mixed>> the `components[]` entries
     */
    private function statusComponents(TestResponse $response): array
    {
        /** @var list<array<string, mixed>> $components */
        $components = $response->json('data.components');

        return $components;
    }

    /**
     * Extract one component's payload from a status response.
     *
     * @param  TestResponse<JsonResponse> $response  the status response
     * @param  string                     $component the component slug to extract
     * @return array<string, mixed>       the component's `components[]` entry
     */
    private function componentPayload(TestResponse $response, string $component): array
    {
        $payload = collect($this->statusComponents($response))->firstWhere('component', $component);
        $this->assertNotNull($payload);

        return $payload;
    }

    /**
     * Freeze time so the history windows and seeded `checked_at` rows line up.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-06 12:00:00', 'UTC'));
    }

    /**
     * Release the frozen time after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /*
     * Response Structure Tests
     * ------------------------
     */

    /**
     * Serve the status page without any authentication.
     */
    #[Test]
    public function it_serves_the_status_page_without_authentication(): void
    {
        // Arrange

        SystemHealthCheck::factory()->create(['checked_at' => Carbon::now('UTC')]);

        // Act

        $response = $this->getJson('/api/status');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('message', 'System Status Retrieved Successfully');
        $response->assertJsonPath('data.monitoring_active', true);
        $response->assertJsonPath('data.overall_status', 'up');

        $components = collect($this->statusComponents($response));

        $this->assertSame(['database', 'cache', 'queue'], $components->pluck('component')->all());
        $this->assertSame(['Database', 'Cache', 'Queue'], $components->pluck('label')->all());
    }

    /**
     * List every monitored component with a null current status before the
     * first scheduled run has ever persisted a row.
     */
    #[Test]
    public function it_reports_no_monitoring_data_before_the_first_recorded_run(): void
    {
        // Act

        $response = $this->getJson('/api/status');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.monitoring_active', false);
        $response->assertJsonPath('data.overall_status', 'up');

        foreach ($this->statusComponents($response) as $component) {
            $this->assertNull($component['status']);
            $this->assertNull($component['checked_at']);
            $this->assertNull($component['uptime_percentage']);
            $this->assertNotEmpty($component['history']);
        }
    }

    /**
     * Return the latest recorded row per component, not the first.
     */
    #[Test]
    public function it_uses_the_latest_row_per_component_for_the_current_status(): void
    {
        // Arrange

        SystemHealthCheck::factory()->forComponent('database')->create([
            'status' => SystemHealthStatus::Up,
            'checked_at' => Carbon::now('UTC')->subMinutes(10),
        ]);
        SystemHealthCheck::factory()->forComponent('database')->create([
            'status' => SystemHealthStatus::Down,
            'message' => 'Connection refused',
            'checked_at' => Carbon::now('UTC'),
        ]);

        // Act

        $response = $this->getJson('/api/status');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.overall_status', 'down');
        $this->assertSame('down', $this->componentPayload($response, 'database')['status']);
    }

    /*
     * Overall Rollup Tests
     * --------------------
     */

    /**
     * Reduce every component's current status to the single worst reading.
     *
     * @param array<string, string> $statuses        the per-component current statuses
     * @param string                $expectedOverall the rollup the map must produce
     */
    #[DataProvider('worstStatusProvider')]
    #[Test]
    public function it_reports_the_worst_component_status_as_the_overall_status(array $statuses, string $expectedOverall): void
    {
        // Arrange

        foreach ($statuses as $component => $status) {
            SystemHealthCheck::factory()->forComponent($component)->create([
                'status' => SystemHealthStatus::from($status),
                'checked_at' => Carbon::now('UTC'),
            ]);
        }

        // Act

        $response = $this->getJson('/api/status');

        // Assert

        $response->assertOk();
        $response->assertJsonPath('data.overall_status', $expectedOverall);
    }

    /*
     * History Tests
     * -------------
     */

    /**
     * Aggregate the daily uptime history per component over the requested window.
     *
     * Seeded window (days=3): Sep 4 Up; Sep 5 Down; Sep 6 two Up + one Down -
     * giving per-day uptimes of 100.0, 0.0, and 66.7, and a whole-window
     * aggregate of 3/5 = 60.0 (not the average of the daily figures).
     */
    #[Test]
    public function it_builds_daily_uptime_history_over_the_requested_window(): void
    {
        // Arrange

        SystemHealthCheck::factory()->forComponent('database')->create([
            'checked_at' => Carbon::parse('2026-09-04 08:00:00', 'UTC'),
        ]);
        SystemHealthCheck::factory()->forComponent('database')->down()->create([
            'checked_at' => Carbon::parse('2026-09-05 08:00:00', 'UTC'),
        ]);
        SystemHealthCheck::factory()->forComponent('database')->count(2)->create([
            'checked_at' => Carbon::parse('2026-09-06 08:00:00', 'UTC'),
        ]);
        SystemHealthCheck::factory()->forComponent('database')->down()->create([
            'checked_at' => Carbon::parse('2026-09-06 10:00:00', 'UTC'),
        ]);

        // Act

        $response = $this->getJson('/api/status?days=3');

        // Assert

        $response->assertOk();

        $database = $this->componentPayload($response, 'database');

        /*
         * JSON numbers decode whole floats as ints (`60.0` encodes as `60`),
         * so percentages compare by value, not by PHP type.
         */
        $this->assertEqualsWithDelta(60.0, $database['uptime_percentage'], 0.01);

        /** @var list<array<string, mixed>> $history */
        $history = $database['history'];

        $this->assertCount(3, $history);
        $this->assertSame(['2026-09-04', '2026-09-05', '2026-09-06'], collect($history)->pluck('date')->all());
        $this->assertSame('up', $history[0]['status']);
        $this->assertEqualsWithDelta(100.0, $history[0]['uptime_percentage'], 0.01);
        $this->assertSame('down', $history[1]['status']);
        $this->assertEqualsWithDelta(0.0, $history[1]['uptime_percentage'], 0.01);
        $this->assertSame('down', $history[2]['status']);
        $this->assertEqualsWithDelta(66.7, $history[2]['uptime_percentage'], 0.01);
    }

    /**
     * Emit one null-filled entry per calendar day with no recorded checks,
     * rather than omitting the day or fabricating an Up reading.
     */
    #[Test]
    public function it_fills_days_without_checks_with_null_entries(): void
    {
        // Arrange

        SystemHealthCheck::factory()->forComponent('queue')->create([
            'checked_at' => Carbon::parse('2026-09-04 08:00:00', 'UTC'),
        ]);

        // Act

        $response = $this->getJson('/api/status?days=3');

        // Assert

        $response->assertOk();

        /** @var list<array<string, mixed>> $history */
        $history = $this->componentPayload($response, 'queue')['history'];

        $this->assertCount(3, $history);
        $this->assertSame('up', $history[0]['status']);
        $this->assertEqualsWithDelta(100.0, $history[0]['uptime_percentage'], 0.01);
        $this->assertNull($history[1]['uptime_percentage']);
        $this->assertNull($history[1]['status']);
        $this->assertNull($history[2]['uptime_percentage']);
        $this->assertNull($history[2]['status']);
    }

    /*
     * Validation Tests
     * ----------------
     */

    /**
     * Reject a `days` value outside the 1-90 window.
     */
    #[DataProvider('invalidDaysProvider')]
    #[Test]
    public function it_rejects_a_days_value_outside_the_allowed_window(string $days): void
    {
        // Act

        $response = $this->getJson('/api/status?days='.$days);

        // Assert

        $this->assertApiValidationErrors($response, ['days']);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Per-component status maps and the overall reading each must produce.
     *
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public static function worstStatusProvider(): array
    {
        return [
            'down beats degraded and up' => [
                ['database' => 'up', 'cache' => 'degraded', 'queue' => 'down'],
                'down',
            ],
            'degraded beats up when no component is down' => [
                ['database' => 'up', 'cache' => 'degraded', 'queue' => 'up'],
                'degraded',
            ],
            'all components up stays up' => [
                ['database' => 'up', 'cache' => 'up', 'queue' => 'up'],
                'up',
            ],
        ];
    }

    /**
     * `days` values outside the 1-90 window.
     *
     * @return array<string, array{0: string}>
     */
    public static function invalidDaysProvider(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
            'above the cap' => ['91'],
            'not an integer' => ['abc'],
        ];
    }
}
