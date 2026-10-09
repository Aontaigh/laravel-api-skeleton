<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Support\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Proves a rate-limit counter reaches a shared cache store.
 *
 * Every other rate-limit suite runs with `CACHE_STORE=array` ([phpunit.xml],
 * [.env.testing.local]), so a limiter that only counts inside the test process
 * still passes them. This class boots with `CACHE_STORE=database` - the shared
 * `cache`-table store, which is what [config/cache.php] resolves to when the
 * variable is unset - and asserts the counter row lands in the `cache` table.
 * That row is what makes an allowance real across tasks and what a per-process
 * store cannot provide.
 */
#[CoversClass(ApiResponse::class)]
final class ApiRateLimitSharedStoreTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /**
     * The `CACHE_STORE` values replaced for this class, so they can be restored.
     *
     * @var array{server: string|null, env: string|null, putenv: string|null}|null
     */
    private ?array $originalCacheStoreEnvironment = null;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Boot the application on the database cache store.
     *
     * Exported into every channel `Illuminate\Support\Env` reads - `$_SERVER`,
     * `$_ENV`, and the process environment - before the application is created,
     * so `config('cache.default')` is `database` from the first request. The
     * original values are restored in `tearDown()` so the rest of the suite keeps
     * the `array` store.
     *
     * @return Application the booted application
     */
    public function createApplication(): Application
    {
        $server = $_SERVER['CACHE_STORE'] ?? null;
        $env = $_ENV['CACHE_STORE'] ?? null;
        $putenv = getenv('CACHE_STORE');

        $this->originalCacheStoreEnvironment = [
            'server' => is_string($server) ? $server : null,
            'env' => is_string($env) ? $env : null,
            'putenv' => is_string($putenv) ? $putenv : null,
        ];

        $_SERVER['CACHE_STORE'] = 'database';
        $_ENV['CACHE_STORE'] = 'database';
        putenv('CACHE_STORE=database');

        return parent::createApplication();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Record the limiter's counter in the shared `cache` table.
     */
    #[Test]
    public function it_records_the_counter_in_the_cache_table(): void
    {
        // Arrange

        config(['api.status_rate_limit_per_minute' => 1]);

        // Act

        $this->getJson('/api/status')->assertOk();

        /** @var TestResponse<JsonResponse> $response */
        $response = $this->getJson('/api/status');

        // Assert

        $response->assertStatus(429);
        $this->assertSame('database', config('cache.default'));

        /*
         * The middleware hashes the limiter name and key, and the store keeps
         * `<key>` (the hit count) beside `<key>:timer` (the window expiry). The
         * timer row is asserted because it only exists once the limiter has
         * written to the store, and the store here is the `cache` table.
         */
        $counterKey = md5('api-status'.'127.0.0.1');

        $this->assertTrue(
            DB::table('cache')->where('key', 'like', '%'.$counterKey.':timer')->exists(),
            'Expected the rate-limiter counter row in the `cache` table.',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Protected
    |--------------------------------------------------------------------------
    */

    /**
     * Restore the original `CACHE_STORE` values and tear the application down.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $original = $this->originalCacheStoreEnvironment;

        if ($original !== null) {
            if ($original['server'] === null) {
                unset($_SERVER['CACHE_STORE']);
            } else {
                $_SERVER['CACHE_STORE'] = $original['server'];
            }

            if ($original['env'] === null) {
                unset($_ENV['CACHE_STORE']);
            } else {
                $_ENV['CACHE_STORE'] = $original['env'];
            }

            if ($original['putenv'] === null) {
                putenv('CACHE_STORE');
            } else {
                putenv('CACHE_STORE='.$original['putenv']);
            }
        }

        parent::tearDown();
    }
}
