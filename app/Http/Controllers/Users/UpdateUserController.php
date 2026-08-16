<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\UpdateUserAction;
use App\DataTransferObjects\Users\UpdateUserData;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Updates a User.
 *
 * @example
 * PATCH /api/users/{user} {"name": "Updated Name"}
 */
final class UpdateUserController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Update the given User.
     *
     * @param  UpdateUserRequest $request the validated update request
     * @param  User              $user    the User being updated (route-bound)
     * @param  UpdateUserAction  $action  the update Action
     * @return JsonResponse      the standardised success envelope
     */
    public function __invoke(
        UpdateUserRequest $request,
        User $user,
        UpdateUserAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        $data = new UpdateUserData(
            user: $user,
            name: $input->has('name') ? $input->string('name')->toString() : null,
            teamId: $input->has('team_id') ? $input->integer('team_id') : null,
        );

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $updatedUser = $action->execute($data);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: new UserResource($updatedUser),
            message: 'User Updated Successfully',
        );
    }
}
