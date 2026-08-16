<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a Token ability list contains unknown or invalid permission names.
 */
final class InvalidTokenAbilitiesException extends InvalidArgumentException
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new InvalidTokenAbilitiesException.
     *
     * @param list<string> $invalidAbilities the ability names that are not in the catalog
     */
    public function __construct(
        private readonly array $invalidAbilities,
    ) {
        parent::__construct('Invalid Token Abilities');
    }

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Get the ability names that failed validation.
     *
     * @return list<string> the unknown or disallowed ability names
     */
    public function invalidAbilities(): array
    {
        return $this->invalidAbilities;
    }
}
