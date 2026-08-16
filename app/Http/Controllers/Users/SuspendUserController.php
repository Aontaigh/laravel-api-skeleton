<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\SuspendUserAction;
use App\Http\Requests\Users\SuspendUserRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Suspends a User's account.
 *
 * @example
 * POST /api/users/{user}/suspend
 */
final class SuspendUserController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Suspend the given User.
     *
     * @param  SuspendUserRequest $request the validated suspend request
     * @param  User               $user    the User being suspended (route-bound)
     * @param  SuspendUserAction  $action  the suspend Action
     * @return JsonResponse       the standardised success envelope
     */
    public function __invoke(
        SuspendUserRequest $request,
        User $user,
        SuspendUserAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $action->execute($user);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(data: null, message: 'User Suspended Successfully');
    }
}
