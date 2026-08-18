<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Actions\Sessions\RevokeAllWebSessionsForUserAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Ends every active authentication session for a User.
 */
final class LogoutUserAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * @param RevokeAllWebSessionsForUserAction $revokeAllWebSessions clears the per-user session registry
     */
    public function __construct(
        private readonly RevokeAllWebSessionsForUserAction $revokeAllWebSessions,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Revoke all tokens, clear remember-me state, and destroy server sessions.
     *
     * The session-version bump is the driver-agnostic part: it signs the User
     * out of every web session regardless of the session driver, so the
     * `sessions` table delete below is a best-effort cleanup for the DB driver
     * rather than the only mechanism.
     *
     * @example
     * app(LogoutUserAction::class)->execute($user, $request);
     *
     * @param User         $user    the User ending their session
     * @param Request|null $request the inbound HTTP request when the User is logging themselves out
     */
    public function execute(User $user, ?Request $request = null): void
    {
        $user->tokens()->delete();

        $user->forceFill(['remember_token' => null])->save();

        $user->rotateSessions();

        $this->revokeAllWebSessions->execute($user);

        DB::table(config()->string('session.table'))
            ->where('user_id', $user->id)
            ->delete();

        if ($request !== null && $request->hasSession()) {
            $requestUser = $request->user();

            if ($requestUser instanceof User && $requestUser->is($user)) {
                Auth::guard('web')->logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }
    }
}
