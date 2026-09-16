<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DataTransferObjects\Auth\ForceLogoutUsersData;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Models\User;
use App\Support\RequestId;
use Illuminate\Http\Request;

/**
 * Ends every active authentication session for the given Users.
 */
final class ForceLogoutUsersAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new ForceLogoutUsersAction.
     *
     * @param LogoutUserAction      $logoutUser revokes tokens, remember-me state, and sessions
     * @param RecordAuthAuditAction $audit      records forced-logout audit events
     */
    public function __construct(
        private readonly LogoutUserAction $logoutUser,
        private readonly RecordAuthAuditAction $audit,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Force-logout every User ID in the payload.
     *
     * @example
     * app(ForceLogoutUsersAction::class)->execute($data, $request);
     *
     * @param  ForceLogoutUsersData $data    the target User IDs
     * @param  Request              $request the inbound admin request
     * @return list<int>            the User IDs that were logged out
     */
    public function execute(ForceLogoutUsersData $data, Request $request): array
    {
        /** @var list<int> $loggedOutIds */
        $loggedOutIds = [];

        $users = User::query()
            ->withTrashed()
            ->whereIn('id', $data->userIds)
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            /*
             * Revoke first, then audit: recording ForcedLogout before the
             * credentials are gone would report success even if logout threw.
             */
            $this->logoutUser->execute($user);

            /*
             * The acting admin is recorded in `actor_user_id` (they differ from
             * `user_id`, the forced-out account), so "which admin revoked that
             * session?" is answerable from the audit table alone.
             */
            $this->audit->execute(new RecordAuthAuditData(
                event: AuthAuditEvent::ForcedLogout,
                userId: $user->id,
                actorUserId: $request->user()?->id,
                email: $user->email,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                requestId: RequestId::current($request),
            ));

            $loggedOutIds[] = $user->id;
        }

        return $loggedOutIds;
    }
}
