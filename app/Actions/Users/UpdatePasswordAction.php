<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Sessions\InvalidateStoredSessionAction;
use App\Actions\Sessions\RevokeOtherWebSessionsForUserAction;
use App\DataTransferObjects\Users\UpdatePasswordData;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Changes the authenticated User's password.
 */
final class UpdatePasswordAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new UpdatePasswordAction.
     *
     * @param RevokeOtherWebSessionsForUserAction $revokeOtherSessions     prunes other registry rows
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
     * Verify the current password and set the new one.
     *
     * The change is a full credential rotation: every Personal Access Token is
     * revoked (PATs are not governed by `session_version`), every other
     * registered web session is stamped revoked, and the User's
     * `session_version` is bumped so stale cookies die on the next request.
     * The current session ID is excluded from the registry prune so the caller
     * stays signed in; its row is restamped by the caller afterwards.
     *
     * Stored payload destruction runs in `DB::afterCommit` - the session store
     * is not a transaction participant, so destroying before commit would leave
     * ghost registry rows if the save rolled back. A store-destroy failure is
     * fail-closed: `failClosed()` bumps `session_version` once more so every
     * cookie dies even though a payload survived in the store.
     *
     * @example
     * app(UpdatePasswordAction::class)->execute($user, $data);
     *
     * @param  User               $user            the authenticated User
     * @param  UpdatePasswordData $data            the validated password payload
     * @param  string|null        $exceptSessionId the Laravel session ID to keep, or null
     * @return User               the refreshed User
     *
     * @throws ValidationException when the current password does not match
     */
    public function execute(User $user, UpdatePasswordData $data, ?string $exceptSessionId = null): User
    {
        if (! Hash::check($data->currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The Current Password Is Incorrect'],
            ]);
        }

        return DB::transaction(function () use ($user, $data, $exceptSessionId): User {
            $user->password = $data->newPassword;
            $user->save();

            /*
             * PATs are revoked because they sit outside `session_version`. Web
             * sessions are stamped revoked and their payloads destroyed after
             * commit; rotateSessions covers cookie recall as a final backstop.
             */
            $user->tokens()->delete();
            $user->rotateSessions();

            $sessionIdsToDestroy = $this->revokeOtherSessions->execute($user, $exceptSessionId);

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
