<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Tokens;

/**
 * Validated filter inputs for Token list queries.
 */
final readonly class TokenFilters
{
    /**
     * Create a new TokenFilters value object.
     *
     * @param string|null $search optional name search term
     */
    public function __construct(
        public ?string $search = null,
    ) {}
}
