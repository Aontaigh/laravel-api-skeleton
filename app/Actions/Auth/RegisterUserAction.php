<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DataTransferObjects\Auth\RegisterUserData;
use App\Enums\RoleName;
use App\Models\User;
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
                'email' => $data->email,
                'password' => $data->password,
                'team_id' => null,
                'email_verified_at' => null,
            ]);

            $user->assignRole(RoleName::User->value);

            return $user;
        });
    }
}
