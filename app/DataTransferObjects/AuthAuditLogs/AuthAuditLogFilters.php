<?php

declare(strict_types=1);

namespace App\DataTransferObjects\AuthAuditLogs;

use App\Enums\AuthAuditEvent;

/**
 * Validated filters for the auth audit log index.
 */
final readonly class AuthAuditLogFilters
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new AuthAuditLogFilters.
     *
     * @param string|null         $search      optional partial email match
     * @param AuthAuditEvent|null $event       optional exact event filter
     * @param int|null            $userId      optional user ID filter
     * @param int|null            $apiClientId optional API client ID filter
     */
    public function __construct(
        public ?string $search = null,
        public ?AuthAuditEvent $event = null,
        public ?int $userId = null,
        public ?int $apiClientId = null,
    ) {}
}
