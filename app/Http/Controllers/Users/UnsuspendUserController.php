<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\UnsuspendUserAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Enums\WebhookEvent;
use App\Events\AuthEventOccurred;
use App\Events\WebhookEventDispatched;
use App\Http\Requests\Users\UnsuspendUserRequest;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\RequestId;
use Illuminate\Http\JsonResponse;

/**
 * Lifts a User's account suspension.
 *
 * @example
 * POST /api/users/{user}/unsuspend
 */
final class UnsuspendUserController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Unsuspend the given User.
     *
     * @param  UnsuspendUserRequest $request the validated unsuspend request
     * @param  User                 $user    the User being unsuspended (route-bound)
     * @param  UnsuspendUserAction  $action  the unsuspend Action
     * @return JsonResponse         the standardised success envelope
     */
    public function __invoke(
        UnsuspendUserRequest $request,
        User $user,
        UnsuspendUserAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $action->execute($user);

        event(
            new WebhookEventDispatched(
                event: WebhookEvent::UserUnsuspended,
                data: [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ),
        );

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::UserUnsuspended,
            userId: $user->id,
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

        return ApiResponse::success(data: null, message: 'User Unsuspended Successfully');
    }
}
