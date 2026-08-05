<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DataTransferObjects\Users\UpdateUserData;
use App\Models\User;

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

        $data->user->update($attributes);

        return $data->user->refresh();
    }
}
