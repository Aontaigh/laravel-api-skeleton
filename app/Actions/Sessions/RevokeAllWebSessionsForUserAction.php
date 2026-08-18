<?php

declare(strict_types=1);

namespace App\Actions\Sessions;

use App\Models\User;
use App\Models\WebSession;
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
     * @param InvalidateStoredSessionAction $invalidateStoredSession destroys Laravel session payloads
     */
    public function __construct(
        private readonly InvalidateStoredSessionAction $invalidateStoredSession,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Mark every active registry row revoked and destroy each stored session.
     *
     * @param User $user the User whose web sessions should end
     */
    public function execute(User $user): void
    {
        $activeSessions = WebSession::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->get();

        DB::transaction(function () use ($activeSessions): void {
            foreach ($activeSessions as $webSession) {
                $webSession->forceFill(['revoked_at' => now()])->save();

                $this->invalidateStoredSession->execute($webSession->session_id);
            }
        });
    }
}
