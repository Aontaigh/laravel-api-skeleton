<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Auth\ForceLogoutUsersAction;
use App\DataTransferObjects\Auth\ForceLogoutUsersData;
use App\Http\Requests\Users\ForceLogoutUsersRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Force-logout Users by id — revokes every token, session, and remember-me state.
 *
 * @example
 * POST /api/users/logout {"ids": [2, 5]}
 */
final class ForceLogoutUsersController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * End every session for the requested Users.
     *
     * @param  ForceLogoutUsersRequest $request     the validated force-logout request
     * @param  ForceLogoutUsersAction  $forceLogout the bulk logout Action
     * @return JsonResponse            the standardised success envelope
     */
    public function __invoke(
        ForceLogoutUsersRequest $request,
        ForceLogoutUsersAction $forceLogout,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        /** @var list<int> $userIds */
        $userIds = $input->array('ids');

        $data = new ForceLogoutUsersData(userIds: $userIds);

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $loggedOutIds = $forceLogout->execute($data, $request);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: ['user_ids' => $loggedOutIds],
            message: 'Users Logged Out Successfully',
        );
    }
}
