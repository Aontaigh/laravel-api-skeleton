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
     * @param WebSession   $webSession the registry row being revoked
     * @param Request|null $request    the inbound HTTP request when revoking the current browser
     */
    public function execute(WebSession $webSession, ?Request $request = null): void
    {
        if ($webSession->isRevoked()) {
            return;
        }

        $webSession->forceFill(['revoked_at' => now()])->save();

        $this->invalidateStoredSession->execute($webSession->session_id);

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
