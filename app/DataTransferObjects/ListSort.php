<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * Normalised sort column and direction for list endpoints.
 */
final readonly class ListSort
{
    /**
     * Create a new ListSort value object.
     *
     * @param string       $column    a whitelisted database column name
     * @param 'asc'|'desc' $direction the sort direction
     */
    public function __construct(
        public string $column,
        public string $direction,
    ) {}
}
