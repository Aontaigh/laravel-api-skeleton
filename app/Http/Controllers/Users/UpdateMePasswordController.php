<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\UpdatePasswordAction;
use App\DataTransferObjects\Users\UpdatePasswordData;
use App\Http\Requests\Users\UpdateMePasswordRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Changes the authenticated User's password.
 *
 * @example
 * PATCH /api/me/password {"current_password": "...", "password": "...", "password_confirmation": "..."}
 */
final class UpdateMePasswordController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Change the caller's own password.
     *
     * @param  UpdateMePasswordRequest $request the validated password change request
     * @param  UpdatePasswordAction    $action  the password change Action
     * @return JsonResponse            the standardised success envelope
     */
    public function __invoke(
        UpdateMePasswordRequest $request,
        UpdatePasswordAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        $data = new UpdatePasswordData(
            currentPassword: $input->string('current_password')->toString(),
            newPassword: $input->string('password')->toString(),
        );

        /** @var User $user — never null behind `auth:sanctum` */
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $action->execute($user, $data);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: null,
            message: 'Password Updated Successfully',
        );
    }
}
