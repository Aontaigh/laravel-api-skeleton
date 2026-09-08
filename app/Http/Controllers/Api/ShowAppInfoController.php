<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ShowAppInfoRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Returns public application metadata for deploy verification.
 *
 * @example
 * GET /api/app-info
 */
final class ShowAppInfoController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return the application name, environment, runtime, and driver map.
     *
     * Public and unauthenticated: every value here is non-sensitive
     * operational metadata (names and versions, never secrets or keys).
     * Deploy pipelines hit this after a release to prove the new code is
     * serving with the expected drivers.
     *
     * @param  ShowAppInfoRequest $request the empty app-info request
     * @return JsonResponse       the standardised success envelope
     */
    public function __invoke(ShowAppInfoRequest $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: [
                'app' => [
                    'name' => config()->string('app.name'),
                    'environment' => config()->string('app.env'),
                    'debug' => config()->boolean('app.debug'),
                    'url' => config()->string('app.url'),
                    'locale' => config()->string('app.locale'),
                    'timezone' => config()->string('app.timezone'),
                ],
                'php' => [
                    'version' => PHP_VERSION,
                    'sapi' => PHP_SAPI,
                ],
                'server' => [
                    'os' => PHP_OS_FAMILY,
                    'architecture' => php_uname('m'),
                ],
                'laravel' => [
                    'version' => app()->version(),
                ],
                'drivers' => [
                    'cache' => $this->driver('cache.default'),
                    'session' => $this->driver('session.driver'),
                    'queue' => $this->driver('queue.default'),
                    'database' => $this->driver('database.default'),
                    'mail' => $this->driver('mail.default'),
                    'filesystem' => $this->driver('filesystems.default'),
                ],
            ],
            message: 'App Info Retrieved Successfully',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Read a driver config value as a nullable string.
     *
     * `config()->string()` throws when the value is null, but a null default
     * connection is legitimate, so this returns null instead of blowing up
     * the whole response.
     *
     * @param  string      $key the dot-notation config key to read
     * @return string|null the config value when it is a string, otherwise null
     */
    private function driver(string $key): ?string
    {
        $value = config($key);

        return is_string($value) ? $value : null;
    }
}
