<?php

declare(strict_types=1);

namespace App\Queries\Sessions;

use App\Models\User;
use App\Models\WebSession;

/**
 * Resolves the caller's current registry row from the inbound session ID.
 */
final class CurrentWebSessionQuery
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the caller's current, non-revoked Web Session, if any.
     *
     * A null session ID means the caller is stateless (bearer-token only), so
     * no registry row can belong to the request.
     *
     * @param  User            $user      the authenticated caller
     * @param  string|null     $sessionId the inbound Laravel session ID
     * @return WebSession|null the current browser session row, or null when unregistered
     */
    public function resolve(User $user, ?string $sessionId): ?WebSession
    {
        if ($sessionId === null) {
            return null;
        }

        /** @var WebSession|null $webSession */
        $webSession = WebSession::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->whereNull('revoked_at')
            ->first();

        return $webSession;
    }
}
