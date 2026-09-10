<?php

declare(strict_types=1);

namespace App\Actions\Sessions;

use App\Http\Middleware\EnsureSessionVersionMatches;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Throwable;

/**
 * Destroys a Laravel session in the active session store.
 */
final class InvalidateStoredSessionAction
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * Length of the truncated SHA-256 fingerprint used in place of the raw ID.
     *
     * The Laravel session ID is a secret (the cookie value); logs never carry it.
    /**
     * Length of the truncated SHA-256 fingerprint used in place of the raw ID.
     * The Laravel session ID is a secret (the cookie value); logs never carry it.
     */
    private const int FINGERPRINT_LENGTH = 12;

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
     * Fail-closed: a store that reports failure (false return or an exception)
     * does not throw - the caller learns the payload survived so it can fall
     * back to {@see failClosed()} and recall the User's cookies instead.
     *
     * @param  string    $sessionId the Laravel session ID to destroy
     * @param  User|null $user      the session owner, for structured logs
     * @return bool      true when the store reported a successful destroy
     */
    public function execute(string $sessionId, ?User $user = null): bool
    {
        try {
            $destroyed = Session::getHandler()->destroy($sessionId);
        } catch (Throwable $exception) {
            $this->logDestroyFailure($user, $sessionId, $exception->getMessage());

            return false;
        }

        if ($destroyed !== true) {
            $this->logDestroyFailure($user, $sessionId, null);

            return false;
        }

        if (config()->string('session.driver') !== 'database') {
            return true;
        }

        try {
            DB::table(config()->string('session.table'))
                ->where('id', $sessionId)
                ->delete();
        } catch (QueryException) {
            /*
             * The database driver is selected but the sessions table is missing
             * or unreadable. The handler destroy already removed the payload.
             */
        }

        return true;
    }

    /**
     * Bump `session_version` once so a store-destroy failure still kills cookies.
     *
     * Call this once per Action invocation after any failed destroy, not once
     * per session in a bulk loop. Successful destroys must not rotate: password
     * changes and resets already bump on the happy path.
     *
     * `rotateSessions` is a global stamp; payload destruction is per session
     * ID. If the failing destroy belonged to this User's current cookie (same
     * actor), restamp the live session so the caller is not kicked out by
     * their own revocation - and an admin revoking a victim User must never
     * restamp the admin cookie.
     *
     * @param  User $user the User whose cookies must die on the next version check
     * @return void
     */
    public function failClosed(User $user): void
    {
        $user->rotateSessions();

        $actorId = Auth::id();

        if (session()->isStarted() && is_numeric($actorId) && (int) $actorId === $user->id) {
            session()->put(EnsureSessionVersionMatches::SESSION_KEY, $user->session_version);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Write a structured destroy-failure log line without leaking the session ID.
     *
     * @param  User|null   $user      the session owner when known
     * @param  string      $sessionId the raw Laravel session ID (fingerprinted before logging)
     * @param  string|null $reason    the exception message when a store threw
     * @return void
     */
    private function logDestroyFailure(?User $user, string $sessionId, ?string $reason): void
    {
        Log::warning('Session Payload Destroy Failed', [
            'user_id' => $user?->id,
            'session_fingerprint' => substr(hash('sha256', $sessionId), 0, self::FINGERPRINT_LENGTH),
            'reason' => $reason,
        ]);
    }
}
