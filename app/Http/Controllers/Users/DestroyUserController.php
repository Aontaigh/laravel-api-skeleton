<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\SoftDeleteUserAction;
use App\Enums\WebhookEvent;
use App\Events\WebhookEventDispatched;
use App\Http\Requests\Users\DestroyUserRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Soft-deletes a User.
 *
 * @example
 * DELETE /api/users/{user}
 */
final class DestroyUserController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Soft-delete the given User.
     *
     * The soft-deleted identity is announced as `user.deleted` so subscribed
     * integrators can purge or anonymise their local copies of the account.
     *
     * @param  DestroyUserRequest   $request the validated delete request
     * @param  User                 $user    the User being deleted (route-bound)
     * @param  SoftDeleteUserAction $action  the soft-delete Action
     * @return JsonResponse         the standardised success envelope
     */
    public function __invoke(
        DestroyUserRequest $request,
        User $user,
        SoftDeleteUserAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $action->execute($user);

        event(new WebhookEventDispatched(
            event: WebhookEvent::UserDeleted,
            data: [
                'id' => $user->id,
                'email' => $user->email,
            ],
        ));

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(data: null, message: 'User Deleted Successfully');
    }
}
