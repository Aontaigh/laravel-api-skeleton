<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DataTransferObjects\Users\UpdateUserData;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Updates a User's attributes.
 */
final class UpdateUserAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Apply the validated changes and return the refreshed User.
     *
     * @example
     * app(UpdateUserAction::class)->execute($data);
     *
     * @param  UpdateUserData $data the validated update payload
     * @return User           the refreshed User
     */
    public function execute(UpdateUserData $data): User
    {
        $attributes = [];

        if ($data->name !== null) {
            $attributes['name'] = $data->name;
        }

        if ($data->teamId !== null) {
            $attributes['team_id'] = $data->teamId;
        }

        if ($data->phone !== null) {
            $attributes['phone'] = $data->phone;
        }

        $data->user->update($attributes);

        if ($data->role !== null) {
            $this->guardLastAdminDemotion($data->user, $data->role);

            $data->user->syncRoles([$data->role->value]);
        }

        return $data->user->refresh();
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Refuse a role change that would leave no Admin in the system.
     *
     * Demoting the last Admin is irreversible through the API - no remaining
     * account could promote a replacement - so it answers `422` while the
     * Policy answers `403` for who may assign roles at all. Soft-deleted
     * accounts never count: the Spatie role scope honours the SoftDeletes
     * global scope, so a deleted Admin cannot keep the seat warm.
     *
     * @param  User     $user    the User whose role would change
     * @param  RoleName $newRole the requested role
     * @return void
     *
     * @throws ValidationException when the change would remove the last Admin
     */
    private function guardLastAdminDemotion(User $user, RoleName $newRole): void
    {
        if ($newRole === RoleName::Admin || ! $user->hasRole(RoleName::Admin)) {
            return;
        }

        $anotherAdminExists = User::query()
            ->role(RoleName::Admin->value)
            ->where('id', '!=', $user->id)
            ->exists();

        if (! $anotherAdminExists) {
            throw ValidationException::withMessages([
                'role' => ['Cannot Demote The Last Admin'],
            ]);
        }
    }
}
