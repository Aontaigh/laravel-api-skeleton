<?php

declare(strict_types=1);

namespace App\DataTransferObjects\ApiClients;

use App\Models\ApiClient;

/**
 * Result of creating an API client, including the one-time plaintext secret.
 */
final readonly class CreatedApiClientResult
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new CreatedApiClientResult value object.
     *
     * @param ApiClient $client          the persisted client row
     * @param string    $plainTextSecret the one-time plaintext client secret
     */
    public function __construct(
        public ApiClient $client,
        public string $plainTextSecret,
    ) {}
}
