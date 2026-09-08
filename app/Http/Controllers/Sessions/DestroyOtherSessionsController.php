<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sessions;

use App\Actions\Sessions\InvalidateStoredSessionAction;
use App\Actions\Sessions\RevokeOtherWebSessionsForUserAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Sessions\DestroyOtherSessionsRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Revokes every cookie-bound web session except the caller's current browser.
 *
 * @example
 * DELETE /api/sessions/others
 */
final class DestroyOtherSessionsController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Revoke every other registered web session for the caller.
     *
     * Bearer tokens are untouched and `session_version` is not bumped, so the
     * current browser stays signed in. Stored payload destruction runs in
     * `DB::afterCommit` - the session store is not a transaction participant,
     * so destroying before commit would leave ghost registry rows on rollback.
     * A store-destroy failure is fail-closed via `failClosed()`.
     *
     * @param  DestroyOtherSessionsRequest         $request    the validated request
     * @param  RevokeOtherWebSessionsForUserAction $revoke     the revoke-others Action
     * @param  InvalidateStoredSessionAction       $invalidate destroys stored payloads
     * @return JsonResponse                        the standardised success envelope
     */
    public function __invoke(
        DestroyOtherSessionsRequest $request,
        RevokeOtherWebSessionsForUserAction $revoke,
        InvalidateStoredSessionAction $invalidate,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        /** @var User $user the route sits behind the authenticated group */
        $user = $request->user();

        $exceptSessionId = $request->hasSession() ? $request->session()->getId() : null;

        $sessionIds = DB::transaction(
            static fn (): array => $revoke->execute($user, $exceptSessionId),
        );

        DB::afterCommit(static function () use ($sessionIds, $user, $invalidate): void {
            $allDestroyed = true;

            foreach ($sessionIds as $sessionId) {
                if (! $invalidate->execute($sessionId, $user)) {
                    $allDestroyed = false;
                }
            }

            if (! $allDestroyed) {
                $invalidate->failClosed($user);
            }
        });

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::SessionRevoked,
            userId: $user->id,
            email: $user->email,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        ));

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(data: null, message: 'Other Sessions Revoked Successfully');
    }
}
