<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\RecordSystemHealthCommand;
use App\Enums\SystemHealthStatus;
use App\Models\SystemHealthCheck;
use App\Providers\SystemHealthServiceProvider;
use App\Queries\SystemHealth\SystemHealthHistoryQuery;
use App\Services\SystemHealth\Checks\CacheHealthCheck;
use App\Services\SystemHealth\Checks\DatabaseHealthCheck;
use App\Services\SystemHealth\Checks\QueueHealthCheck;
use App\Services\SystemHealth\SystemHealthCheckRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Feature tests for the `health:record` console command.
 */
#[CoversClass(RecordSystemHealthCommand::class)]
#[CoversClass(SystemHealthCheckRegistry::class)]
#[CoversClass(SystemHealthServiceProvider::class)]
#[CoversClass(DatabaseHealthCheck::class)]
#[CoversClass(CacheHealthCheck::class)]
#[CoversClass(QueueHealthCheck::class)]
#[CoversClass(SystemHealthHistoryQuery::class)]
final class RecordSystemHealthCommandTest extends TestCase
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

    /**
     * Persist one Up row per monitored component when every probe succeeds.
     */
    #[Test]
    public function it_persists_one_row_per_component_when_every_probe_succeeds(): void
    {
        // Act

        $exitCode = Artisan::call('health:record');

        $rows = SystemHealthCheck::query()->get();

        // Assert

        $this->assertSame(0, $exitCode);
        $this->assertSame(['database', 'cache', 'queue'], $rows->pluck('component')->all());
        $this->assertContainsOnlyInstancesOf(SystemHealthStatus::class, $rows->pluck('status'));
        $rows->each(fn (SystemHealthCheck $row) => $this->assertSame(SystemHealthStatus::Up, $row->status));
    }

    /**
     * Give every row in one run the same `checked_at` instant.
     *
     * The daily aggregation groups by `DATE(checked_at)`, so a shared instant
     * keeps all components of a run landing in the same calendar day.
     */
    #[Test]
    public function it_records_one_shared_checked_at_instant_for_the_whole_run(): void
    {
        // Act

        Artisan::call('health:record');

        $rows = SystemHealthCheck::query()->get();

        // Assert

        $this->assertNotEmpty($rows);

        $checkedAt = $rows->first()?->checked_at?->toIso8601String();

        $rows->each(fn (SystemHealthCheck $row) => $this->assertSame($checkedAt, $row->checked_at->toIso8601String()));
    }

    /**
     * Still persist a row when a probe fails - a Down component is a recorded
     * fact, not a command failure.
     */
    #[Test]
    public function it_persists_a_down_row_without_failing_when_a_probe_reports_a_failure(): void
    {
        // Arrange

        Cache::shouldReceive('put')->andReturn(true);
        Cache::shouldReceive('get')->andThrow(new RuntimeException('Redis went away'));
        Cache::shouldReceive('forget')->andReturn(true);

        // Act

        $exitCode = Artisan::call('health:record');

        $cacheRow = SystemHealthCheck::query()->where('component', 'cache')->first();

        // Assert

        $this->assertSame(0, $exitCode);
        $this->assertNotNull($cacheRow);
        $this->assertSame(SystemHealthStatus::Down, $cacheRow->status);
        $this->assertSame('Redis went away', $cacheRow->message);
    }
}
