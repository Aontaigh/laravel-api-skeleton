<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DataTransferObjects\Auth\RegisterUserData;
use App\Enums\MfaMethod;
use App\Enums\RoleName;
use App\Models\User;
use App\Support\EmailAddress;
use Illuminate\Support\Facades\DB;

/**
 * Creates a new User with the default User role.
 */
final class RegisterUserAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Persist a new User and assign the default role.
     *
     * @example
     * app(RegisterUserAction::class)->execute($data);
     *
     * @param  RegisterUserData $data the validated registration payload
     * @return User             the created User
     */
    public function execute(RegisterUserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            /** @var User $user */
            $user = User::query()->create([
                'name' => $data->name,
                'email' => EmailAddress::normalise($data->email),
                'password' => $data->password,
                'team_id' => null,
                'email_verified_at' => null,
                'mfa_method' => MfaMethod::Email,
            ]);

            $user->assignRole(RoleName::User->value);

            return $user;
        });
    }
}
