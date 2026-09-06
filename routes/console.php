<?php

declare(strict_types=1);
use Illuminate\Console\Scheduling\Schedule as ConsoleSchedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| GeoIP Database Refresh
|--------------------------------------------------------------------------
|
| Weekly GeoLite2 refresh for `staging` and `production` only. Local and
| PHPUnit `testing` never hit MaxMind. An empty credential set is a no-op:
| the `when()` guard skips the run and lookups fail open without the file.
|
*/

Schedule::command('geoip:update')
    ->weeklyOn(ConsoleSchedule::SUNDAY, '03:15')
    ->timezone('UTC')
    ->environments(['staging', 'production'])
    ->when(fn (): bool => config()->string('geoip.account_id') !== ''
        && config()->string('geoip.license_key') !== '')
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| System Health Recording
|--------------------------------------------------------------------------
|
| Every five minutes except in `local` and `testing` - frequent enough that
| the public status page's daily bars reflect an incident within minutes,
| infrequent enough that three lightweight probes (DB, cache, queue) never
| meaningfully compete with real request traffic. `local` and PHPUnit
| `testing` never run it: local health is not worth recording, and a
| scheduled write during a test run would pollute `system_health_checks`
| outside the tests' own transactions. `withoutOverlapping()` guards
| against a slow probe run still executing when the next five-minute tick
| fires.
|
*/

/*
 * Expressed as a `when()` guard rather than an environment allow-list:
 * this scheduler's `Event` has no `unlessEnvironment()`, and a staging /
 * production allow-list would silently disable the run in any future
 * environment name (e.g. `qa`).
 */
Schedule::command('health:record')
    ->everyFiveMinutes()
    ->timezone('UTC')
    ->when(fn (): bool => ! app()->environment(['local', 'testing']))
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Data Retention Policy
|--------------------------------------------------------------------------
|
| No scheduled pruning is configured: every table currently retains its
| rows indefinitely. `system_health_checks` grows by roughly 864 rows per
| component per day, and `auth_audit_logs` grows with authentication
| traffic - retention is a deliberate product and compliance decision,
| not a scheduler default. When a retention window is agreed, add a
| bounded prune command here and document the window in
| `docs/security-audit.md` and the CHANGELOG.
|
*/
