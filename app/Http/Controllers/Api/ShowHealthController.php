<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ShowHealthRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reports application health: database reachability and app version.
 *
 * Public by design - load balancers and uptime probes call this without a
 * bearer token. It never exposes internal state beyond the binary
 * `up`/`down` database status and the semantic version.
 *
 * @example
 * GET /health
 */
final class ShowHealthController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return the health snapshot.
     *
     * @param  ShowHealthRequest $request the validated probe request
     * @return JsonResponse      the standardised success envelope, or 503 when the database is unreachable
     */
    public function __invoke(ShowHealthRequest $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Probe
        |--------------------------------------------------------------------------
        */

        $databaseUp = $this->databaseIsReachable();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        |
        | Load balancers only need the binary answer; a 503 envelope keeps the
        | standard error shape so monitors can assert on `status_code`.
        |
        */

        if (! $databaseUp) {
            return ApiResponse::error(message: 'Service Unavailable', statusCode: 503);
        }

        return ApiResponse::success(
            data: [
                'database' => 'up',
                'version' => config('app.version'),
            ],
            message: 'Service Healthy',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the primary database connection can execute a query.
     *
     * A `select 1` round-trip catches a dead connection, an unreachable
     * host, and an unreachable database in one probe - `getPdo()` alone
     * only proves the connection object exists.
     *
     * @return bool true when the database answered the probe
     */
    private function databaseIsReachable(): bool
    {
        try {
            DB::select('select 1');
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
