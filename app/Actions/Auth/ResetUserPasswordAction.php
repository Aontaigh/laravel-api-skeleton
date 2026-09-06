<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Actions\Sessions\InvalidateStoredSessionAction;
use App\Actions\Sessions\RevokeOtherWebSessionsForUserAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Resets a User's password from a valid password-reset token.
 */
final class ResetUserPasswordAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new ResetUserPasswordAction.
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
     * Set the new password and rotate every credential derived from the old one.
     *
     * A reset is a full credential rotation performed for an unauthenticated
     * caller: the password is set, the remember token is rotated, every
     * Personal Access Token is revoked (PATs are not governed by
     * `session_version`), every registered web session is stamped revoked, and
     * `session_version` is bumped so stale cookies die on the next request.
     * Passing `null` as the excepted session ID revokes all rows - unlike the
     * self-service change, there is no browser to keep signed in.
     *
     * Stored payload destruction runs in `DB::afterCommit` - the session store
     * is not a transaction participant, so destroying before commit would leave
     * ghost registry rows if the save rolled back. A store-destroy failure is
     * fail-closed: `failClosed()` bumps `session_version` once more so every
     * cookie dies even though a payload survived in the store.
     *
     * @example
     * app(ResetUserPasswordAction::class)->execute($user, $password);
     *
     * @param  User   $user     the User whose password was reset
     * @param  string $password the new plaintext password (hashed on the model)
     * @return User   the refreshed User
     */
    public function execute(User $user, string $password): User
    {
        return DB::transaction(function () use ($user, $password): User {
            $user->password = $password;
            $user->setRememberToken(Str::random(60));
            $user->save();

            $user->tokens()->delete();
            $user->rotateSessions();

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

            return $user->refresh();
        });
    }
}
