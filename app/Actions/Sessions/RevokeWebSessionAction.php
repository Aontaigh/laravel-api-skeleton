<?php

declare(strict_types=1);

namespace App\Actions\Sessions;

use App\Models\WebSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Revokes one registered web session without bumping session_version.
 */
final class RevokeWebSessionAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new RevokeWebSessionAction.
     *
     * @param InvalidateStoredSessionAction $invalidateStoredSession destroys the Laravel session payload
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
     * Mark the registry row revoked and destroy the stored session payload.
     *
     * When the inbound request is bound to the revoked session, the web guard
     * is logged out and the current request session is invalidated.
     *
     * A store-destroy failure is fail-closed: `failClosed()` bumps the
     * owner's `session_version` so every cookie dies even though the payload
     * survived in the store - broader than the surgical revoke, but never
     * weaker.
     *
     * @param  WebSession   $webSession the registry row being revoked
     * @param  Request|null $request    the inbound HTTP request when revoking the current browser
     * @return void
     */
    public function execute(WebSession $webSession, ?Request $request = null): void
    {
        if ($webSession->isRevoked()) {
            return;
        }

        $webSession->forceFill(['revoked_at' => now()])->save();

        $owner = $webSession->user;

        if ($owner !== null && ! $this->invalidateStoredSession->execute($webSession->session_id, $owner)) {
            $this->invalidateStoredSession->failClosed($owner);
        }

        if ($request === null || ! $request->hasSession()) {
            return;
        }

        if ($request->session()->getId() !== $webSession->session_id) {
            return;
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
