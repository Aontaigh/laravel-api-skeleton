<?php

declare(strict_types=1);

namespace App\Actions\Tokens;

use App\DataTransferObjects\Tokens\CreateTokenData;
use App\Services\Permissions\PermissionAbilityCatalog;
use Laravel\Sanctum\NewAccessToken;

/**
 * Issues a new Sanctum Personal Access Token for a User.
 */
final class CreatePersonalAccessTokenAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new Create Personal Access Token Action.
     *
     * @param PermissionAbilityCatalog $abilityCatalog validates abilities against the permission catalog
     */
    public function __construct(
        private readonly PermissionAbilityCatalog $abilityCatalog,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Create a Personal Access Token from the given data.
     *
     * @example
     * $token = app(CreatePersonalAccessTokenAction::class)->execute($data);
     *
     * @param  CreateTokenData $data the Token payload
     * @return NewAccessToken  the newly issued Token, including its one-time plaintext value
     */
    public function execute(CreateTokenData $data): NewAccessToken
    {
        $abilities = $this->abilityCatalog->normalizeTokenAbilities($data->abilities);

        $days = $data->remember
            ? config()->integer('api.remember_token_expiration_days')
            : config()->integer('api.token_expiration_days');
        $expiresAt = $data->expiresAt ?? ($days > 0 ? now()->addDays($days) : null);

        return $data->forUser->createToken($data->name, $abilities, $expiresAt);
    }
}
