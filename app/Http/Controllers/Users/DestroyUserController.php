<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\SoftDeleteUserAction;
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
        | Input
        |--------------------------------------------------------------------------
        */

        $action->execute($user);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(data: null, message: 'User Deleted Successfully');
    }
}
