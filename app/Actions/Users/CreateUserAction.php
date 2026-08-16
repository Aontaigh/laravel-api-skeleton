<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DataTransferObjects\Users\CreateUserData;
use App\Enums\MfaMethod;
use App\Models\User;
use App\Support\EmailAddress;
use Illuminate\Support\Facades\DB;

/**
 * Creates a User with the caller-specified role and team assignment.
 */
final class CreateUserAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Persist a new User with the given role and optional team.
     *
     * @example
     * app(CreateUserAction::class)->execute($data);
     *
     * @param  CreateUserData $data the validated creation payload
     * @return User           the created User
     */
    public function execute(CreateUserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            /** @var User $user */
            $user = User::query()->create([
                'name' => $data->name,
                'email' => EmailAddress::normalise($data->email),
                'password' => $data->password,
                'team_id' => $data->teamId,
                'email_verified_at' => null,
                'mfa_method' => MfaMethod::Email,
            ]);

            $user->assignRole($data->role->value);

            return $user;
        });
    }
}
