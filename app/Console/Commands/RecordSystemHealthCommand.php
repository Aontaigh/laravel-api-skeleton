<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SystemHealthCheck;
use App\Services\SystemHealth\SystemHealthCheckRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Runs every registered {@see \App\Contracts\SystemHealth\SystemHealthCheck}
 * and persists one row per component for the public status page.
 *
 * Scheduled every five minutes except in `local` and `testing` - see
 * `routes/console.php`.
 */
final class RecordSystemHealthCommand extends Command
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'health:record';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run Every System Health Check and Persist One Row per Component';

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Run every registered check and write its result.
     *
     * @param  SystemHealthCheckRegistry $registry every tagged SystemHealthCheck implementation
     * @return int                       always SUCCESS; a Down component is a recorded fact, not a command failure
     */
    public function handle(SystemHealthCheckRegistry $registry): int
    {
        /*
         * One shared timestamp for every row in this run, rather than
         * `now()` per check. SystemHealthHistoryQuery's daily aggregation
         * groups by `DATE(checked_at)`, so giving every component in the same
         * run an identical instant keeps them landing in the same calendar
         * day even when the run straddles midnight UTC mid-way through.
         */
        $checkedAt = Carbon::now('UTC');

        foreach ($registry->all() as $check) {
            $result = $check->check();

            SystemHealthCheck::create([
                'component' => $check->component(),
                'status' => $result->status,
                'response_time_ms' => $result->responseTimeMs,
                'message' => $result->message,
                'checked_at' => $checkedAt,
            ]);

            $this->info(sprintf(
                '[%s] %s: %s',
                $result->status->label(),
                $check->component(),
                $result->message ?? 'OK',
            ));
        }

        return self::SUCCESS;
    }
}
