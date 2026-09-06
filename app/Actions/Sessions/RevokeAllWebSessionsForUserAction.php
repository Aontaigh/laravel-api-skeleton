<?php

declare(strict_types=1);

namespace App\Actions\Sessions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Revokes every registered web session for a User.
 */
final class RevokeAllWebSessionsForUserAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new RevokeAllWebSessionsForUserAction.
     *
     * @param RevokeOtherWebSessionsForUserAction $revokeOtherSessions     stamps registry rows revoked
     * @param InvalidateStoredSessionAction       $invalidateStoredSession destroys stored payloads
     */
    public function __construct(
        private readonly RevokeOtherWebSessionsForUserAction $revokeOtherSessions,
        private readonly InvalidateStoredSessionAction $invalidateStoredSession,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Mark every active registry row revoked; destroy the stored payloads after commit.
     *
     * Delegates the row stamping to the selective revocation Action with no
     * excepted session, then defers payload destruction to `DB::afterCommit`
     * - the session store is not a transaction participant, so destroying
     * inside a transaction would leave ghost registry rows on rollback.
     * Outside a transaction (logout is not wrapped in one) the callback runs
     * immediately, matching the previous inline behaviour.
     *
     * A store-destroy failure is fail-closed: `failClosed()` bumps
     * `session_version` once more so every cookie dies even though a payload
     * survived in the store.
     *
     * @param  User $user the User whose web sessions should end
     * @return void
     */
    public function execute(User $user): void
    {
        $sessionIdsToDestroy = $this->revokeOtherSessions->execute($user, null);

        DB::afterCommit(function () use ($sessionIdsToDestroy, $user): void {
            $allDestroyed = true;

            foreach ($sessionIdsToDestroy as $sessionId) {
                if (! $this->invalidateStoredSession->execute($sessionId, $user)) {
                    $allDestroyed = false;
                }
            }

            if (! $allDestroyed) {
                $this->invalidateStoredSession->failClosed($user);
            }
        });
    }
}
