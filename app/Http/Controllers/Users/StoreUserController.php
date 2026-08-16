<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\CreateUserAction;
use App\DataTransferObjects\Users\CreateUserData;
use App\Enums\RoleName;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Creates a new User account with role and team assignment.
 *
 * @example
 * POST /api/users {"name":"Alice","email":"alice@example.com","password":"SecretPass12","password_confirmation":"SecretPass12","role":"Manager","team_id":1}
 */
final class StoreUserController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Create a user account with the caller-specified role and team.
     *
     * @param  StoreUserRequest $request the validated creation request
     * @param  CreateUserAction $action  the create-user Action
     * @return JsonResponse     the standardised success envelope
     */
    public function __invoke(
        StoreUserRequest $request,
        CreateUserAction $action,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        $data = new CreateUserData(
            name: $input->string('name')->toString(),
            email: $input->string('email')->toString(),
            password: $input->string('password')->toString(),
            role: $input->has('role')
                ? RoleName::from($input->string('role')->toString())
                : RoleName::User,
            teamId: $input->has('team_id') ? $input->integer('team_id') : null,
        );

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $user = $action->execute($data);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: new UserResource($user),
            message: 'User Created Successfully',
            statusCode: 201,
        );
    }
}
