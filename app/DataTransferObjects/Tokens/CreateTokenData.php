<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Tokens;

use App\Models\User;

/**
 * Validated input for issuing a new Sanctum Personal Access Token.
 */
final readonly class CreateTokenData
{
    /*
    |--------------------------------------------------------------------------​
    | Constructor
    |--------------------------------------------------------------------------​
    */

    /**
     * Create a new CreateTokenData value object.
     *
     * @param User         $forUser   the User the Token is issued to
     * @param string       $name      a human-readable label for the Token
     * @param list<string> $abilities the Token's granted abilities
     */
    public function __construct(
        public User $forUser,
        public string $name,
        public array $abilities = ['*'],
    ) {}
}
