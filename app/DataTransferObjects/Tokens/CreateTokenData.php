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
     * @param User                            $forUser                 the User the Token is issued to
     * @param string                          $name                    a human-readable label for the Token
     * @param list<string>                    $abilities               the Token's granted abilities
     * @param bool                            $remember                whether to apply the extended remember-me lifetime
     * @param \Illuminate\Support\Carbon|null $expiresAt               explicit expiration when caller supplies one
     * @param bool                            $useConfiguredExpiration when true, a null {@see $expiresAt} falls back to config
     */
    public function __construct(
        public User $forUser,
        public string $name,
        public array $abilities = ['*'],
        public bool $remember = false,
        public ?\Illuminate\Support\Carbon $expiresAt = null,
        public bool $useConfiguredExpiration = true,
    ) {}
}
