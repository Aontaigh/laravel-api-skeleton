<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DataTransferObjects\Auth\ForceLogoutUsersData;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Models\User;
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
     * Force-logout every User id in the payload.
     *
     * @example
     * app(ForceLogoutUsersAction::class)->execute($data, $request);
     *
     * @param  ForceLogoutUsersData $data    the target User ids
     * @param  Request              $request the inbound admin request
     * @return list<int>            the User ids that were logged out
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
            $this->audit->execute(new RecordAuthAuditData(
                event: AuthAuditEvent::ForcedLogout,
                userId: $user->id,
                email: $user->email,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ));

            $this->logoutUser->execute($user);
            $loggedOutIds[] = $user->id;
        }

        return $loggedOutIds;
    }
}
