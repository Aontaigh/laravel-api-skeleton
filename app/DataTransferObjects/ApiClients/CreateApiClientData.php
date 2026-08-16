<?php

declare(strict_types=1);

namespace App\DataTransferObjects\ApiClients;

/**
 * Validated input for creating a new API client.
 */
final readonly class CreateApiClientData
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new CreateApiClientData value object.
     *
     * @param string       $name      a human-readable label for the client
     * @param list<string> $abilities the Token abilities granted on exchange
     */
    public function __construct(
        public string $name,
        public array $abilities,
    ) {}
}
