<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\UpdateUserAction;
use App\DataTransferObjects\Users\UpdateUserData;
use App\Http\Requests\Users\UpdateMeRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Updates the authenticated User's own profile.
 *
 * @example
 * PATCH /api/me {"name": "Updated Name"}
 */
final class UpdateMeController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Update the caller's own User record.
     *
     * @param  UpdateMeRequest  $request the validated update request
     * @param  UpdateUserAction $action  the update Action
     * @return JsonResponse     the standardised success envelope
     */
    public function __invoke(
        UpdateMeRequest $request,
        UpdateUserAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        /** @var User $user — never null behind `auth:sanctum` */
        $user = $request->user();

        $data = new UpdateUserData(
            user: $user,
            name: $input->has('name') ? $input->string('name')->toString() : null,
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
            message: 'Profile Updated Successfully',
        );
    }
}
