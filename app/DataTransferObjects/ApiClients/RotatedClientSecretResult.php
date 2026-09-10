<?php

declare(strict_types=1);

namespace App\DataTransferObjects\ApiClients;

use App\Models\ApiClient;

/**
 * Result of rotating an API client secret, including the one-time plaintext secret.
 */
final readonly class RotatedClientSecretResult
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new RotatedClientSecretResult value object.
     *
     * @param ApiClient $client          the client row carrying the new secret
     * @param string    $plainTextSecret the one-time plaintext client secret
     */
    public function __construct(
        public ApiClient $client,
        public string $plainTextSecret,
    ) {}
}
