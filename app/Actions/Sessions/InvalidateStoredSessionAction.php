<?php

declare(strict_types=1);

namespace App\Actions\Sessions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * Destroys a Laravel session in the active session store.
 */
final class InvalidateStoredSessionAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Remove the session payload from the configured driver.
     *
     * The database `sessions` table delete is a best-effort companion for the
     * DB driver; Redis and file stores are handled by the session handler.
     *
     * @param string $sessionId the Laravel session id to destroy
     */
    public function execute(string $sessionId): void
    {
        Session::getHandler()->destroy($sessionId);

        DB::table(config()->string('session.table'))
            ->where('id', $sessionId)
            ->delete();
    }
}
