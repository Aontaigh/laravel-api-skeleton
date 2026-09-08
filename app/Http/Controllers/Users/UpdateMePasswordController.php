<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\UpdatePasswordAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\DataTransferObjects\Users\UpdatePasswordData;
use App\Enums\AuthAuditEvent;
use App\Enums\PasswordChangeSource;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Users\UpdateMePasswordRequest;
use App\Models\User;
use App\Notifications\Auth\PasswordChangedNotification;
use App\Services\UserAgent\Contracts\UserAgentParser;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

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
     * @param  UpdateMePasswordRequest $request         the validated password change request
     * @param  UpdatePasswordAction    $action          the password change Action
     * @param  UserAgentParser         $userAgentParser the configured parser driver, resolved from the container
     * @return JsonResponse            the standardised success envelope
     */
    public function __invoke(
        UpdateMePasswordRequest $request,
        UpdatePasswordAction $action,
        UserAgentParser $userAgentParser,
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

        /** @var User $user - never null behind `auth:sanctum` */
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $action->execute($user, $data);

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::PasswordChanged,
            userId: $user->id,
            email: $user->email,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        ));

        /*
        |--------------------------------------------------------------------------
        | Password Changed Notification
        |--------------------------------------------------------------------------
        |
        | Best-effort: queuing the security alert is wrapped so a dispatch-time
        | failure is logged, not a 500, once the password is already saved.
        |
        */

        try {
            $user->notify(new PasswordChangedNotification(
                source: PasswordChangeSource::SelfService,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                userAgentParser: $userAgentParser,
            ));
        } catch (Throwable $exception) {
            Log::warning('Password Changed E-Mail Could Not Be Sent', [
                'user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);
        }

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
