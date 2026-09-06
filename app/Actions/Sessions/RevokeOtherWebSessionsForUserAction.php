<?php

declare(strict_types=1);

namespace App\Actions\Sessions;

use App\Models\User;
use App\Models\WebSession;

/**
 * Revokes every registered web session for a User except the current cookie.
 */
final class RevokeOtherWebSessionsForUserAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Mark every other active registry row revoked; return the IDs to destroy.
     *
     * Called after a password change, where the requesting browser must stay
     * signed in while every other session is killed. Rows are stamped revoked
     * inside the caller's transaction; payload destruction is left to the
     * caller (after commit) because the session store is not a transaction
     * participant - destroying here would leave ghost rows on rollback.
     *
     * @param  User         $user            the User whose other sessions should end
     * @param  string|null  $exceptSessionId the Laravel session ID to keep, or null to revoke all
     * @return list<string> the session IDs whose payloads must be destroyed post-commit
     */
    public function execute(User $user, ?string $exceptSessionId): array
    {
        $query = WebSession::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at');

        if ($exceptSessionId !== null && $exceptSessionId !== '') {
            $query->where('session_id', '!=', $exceptSessionId);
        }

        /** @var list<string> $sessionIds */
        $sessionIds = $query->pluck('session_id')->all();

        if ($sessionIds !== []) {
            WebSession::query()
                ->whereIn('session_id', $sessionIds)
                ->update(['revoked_at' => now()]);
        }

        return $sessionIds;
    }
}
