<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

/**
 * Validated client-credentials payload for token exchange.
 */
final readonly class ClientCredentialsData
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new ClientCredentialsData value object.
     *
     * @param string $clientId     the public client identifier
     * @param string $clientSecret the plaintext client secret
     */
    public function __construct(
        public string $clientId,
        public string $clientSecret,
    ) {}
}
