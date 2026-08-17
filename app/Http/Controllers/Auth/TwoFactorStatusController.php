<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\GetTwoFactorStatusAction;
use App\Http\Requests\Auth\TwoFactorStatusRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Reports whether a pending two-factor challenge is still active.
 *
 * @example
 * GET /api/two-factor/status?two_factor_token=...
 */
final class TwoFactorStatusController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return the pending challenge status for SPA polling.
     *
     * @param  TwoFactorStatusRequest   $request the validated status request
     * @param  GetTwoFactorStatusAction $status  resolves the pending challenge
     * @return JsonResponse             the pending status envelope, or a clear error
     */
    public function __invoke(
        TwoFactorStatusRequest $request,
        GetTwoFactorStatusAction $status,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Params
        |--------------------------------------------------------------------------
        */

        $token = $request->twoFactorToken();

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $outcome = $status->execute($token);

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        if (! $outcome->isSuccess()) {
            return ApiResponse::error(
                message: (string) $outcome->errorMessage,
                statusCode: (int) $outcome->statusCode,
            );
        }

        return ApiResponse::success(
            data: [
                'two_factor_required' => true,
                'expires_at' => (string) $outcome->expiresAt,
            ],
            message: 'Two-Factor Status Retrieved Successfully',
        );
    }
}
