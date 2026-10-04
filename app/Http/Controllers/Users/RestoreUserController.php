<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\RestoreUserAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuditOutcome;
use App\Enums\AuthAuditEvent;
use App\Enums\WebhookEvent;
use App\Events\AuthEventOccurred;
use App\Events\WebhookEventDispatched;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\RestoreUserRequest;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\RequestId;
use Illuminate\Http\JsonResponse;

/**
 * Restores a soft-deleted User record.
 *
 * @example
 * POST /api/users/1/restore
 */
final class RestoreUserController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Restore the route-bound, trashed User.
     *
     * The audit row is recorded after the restore succeeds and carries the
     * acting Admin - a privileged action that un-exiles an account is exactly
     * the one an operator most needs attributed.
     *
     * @param  RestoreUserRequest $request the authorised restore request
     * @param  User               $user    the route-bound, trashed User
     * @param  RestoreUserAction  $restore the restore Action
     * @return JsonResponse       the standardised success envelope
     */
    public function __invoke(
        RestoreUserRequest $request,
        User $user,
        RestoreUserAction $restore,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $restore->execute($user);

        event(new WebhookEventDispatched(
            event: WebhookEvent::UserRestored,
            data: [
                'id' => $user->id,
                'email' => $user->email,
            ],
        ));

        $actor = $request->user();

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::UserRestored,
            outcome: AuditOutcome::Succeeded,
            userId: $user->id,
            actorUserId: $actor instanceof User ? $actor->id : null,
            email: $user->email,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            requestId: RequestId::current($request),
        ));

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(data: null, message: 'User Restored Successfully');
    }
}
