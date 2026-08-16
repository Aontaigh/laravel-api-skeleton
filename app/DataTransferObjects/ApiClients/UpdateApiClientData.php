<?php

declare(strict_types=1);

namespace App\DataTransferObjects\ApiClients;

/**
 * Validated payload for updating an API client.
 *
 * Every field is optional — the caller may send any subset.
 */
final readonly class UpdateApiClientData
{
    /**
     * Create a new UpdateApiClientData value object.
     *
     * @param string|null       $name      the new display name
     * @param list<string>|null $abilities the new scoped abilities
     * @param bool|null         $isActive  whether the client is active
     */
    public function __construct(
        public ?string $name = null,
        public ?array $abilities = null,
        public ?bool $isActive = null,
    ) {}
}
