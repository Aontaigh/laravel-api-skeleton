<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Tokens;

use App\Models\User;

/**
 * Validated filter inputs for Token list queries.
 */
final readonly class TokenFilters
{
    /*
    |--------------------------------------------------------------------------​
    | Constructor
    |--------------------------------------------------------------------------​
    */

    /**
     * Create a new TokenFilters value object.
     *
     * `$viewer` is required: row scoping is derived from it, so allowing
     * null would let a caller silently produce an unscoped result set.
     *
     * @param User        $viewer the authenticated User (drives row scoping)
     * @param string|null $search optional name search term
     */
    public function __construct(
        public User $viewer,
        public ?string $search = null,
    ) {}
}
