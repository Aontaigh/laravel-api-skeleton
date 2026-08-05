<?php

declare(strict_types=1);

namespace App\Actions\Tokens;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * Revokes a Sanctum Personal Access Token.
 */
final class RevokePersonalAccessTokenAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Revoke the given Token.
     *
     * @example
     * app(RevokePersonalAccessTokenAction::class)->execute($token);
     *
     * @param PersonalAccessToken $token the Token to revoke
     */
    public function execute(PersonalAccessToken $token): void
    {
        $token->delete();
    }
}
