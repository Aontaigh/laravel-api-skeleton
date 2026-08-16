<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DataTransferObjects\Users\UpdatePasswordData;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Changes the authenticated User's password.
 */
final class UpdatePasswordAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Verify the current password and set the new one.
     *
     * @example
     * app(UpdatePasswordAction::class)->execute($user, $data);
     *
     * @param  User               $user the authenticated User
     * @param  UpdatePasswordData $data the validated password payload
     * @return User               the refreshed User
     *
     * @throws ValidationException when the current password does not match
     */
    public function execute(User $user, UpdatePasswordData $data): User
    {
        if (! Hash::check($data->currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The Current Password Is Incorrect'],
            ]);
        }

        $user->update([
            'password' => Hash::make($data->newPassword),
        ]);

        $user->rotateSessions();

        return $user->refresh();
    }
}
