<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterUserAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\DataTransferObjects\Auth\RegisterUserData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Auth\RegisterRequest;
use App\Support\ApiResponse;
use App\Support\Auth\PendingTwoFactor;
use Illuminate\Http\JsonResponse;

/**
 * Registers a new User and begins email two-factor verification.
 *
 * @example
 * POST /api/register {"name": "Alice", "email": "alice@example.com", "password": "SecretPass12", "password_confirmation": "SecretPass12"}
 */
final class RegisterController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Create an account and return a pending two-factor challenge.
     *
     * @param  RegisterRequest    $request  the validated registration request
     * @param  RegisterUserAction $register the registration Action
     * @return JsonResponse       the pending-challenge envelope
     */
    public function __invoke(
        RegisterRequest $request,
        RegisterUserAction $register,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $user = $register->execute(new RegisterUserData(
            name: $input->string('name')->toString(),
            email: $input->string('email')->toString(),
            password: $input->string('password')->toString(),
        ));

        $deviceName = $input->filled('device_name')
            ? $input->string('device_name')->toString()
            : null;

        $twoFactorToken = PendingTwoFactor::begin($user->id, shouldRemember: false, deviceName: $deviceName);

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::Register,
            userId: $user->id,
            email: $user->email,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        ));

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: [
                'two_factor_required' => true,
                'two_factor_token' => $twoFactorToken,
            ],
            message: 'Account Created Successfully',
            statusCode: 201,
        );
    }
}
