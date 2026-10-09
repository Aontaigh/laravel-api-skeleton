<?php

declare(strict_types=1);

namespace App\DataTransferObjects\AuthAuditLogs;

use App\Enums\AuthAuditEvent;
use Carbon\CarbonImmutable;

/**
 * Validated filters for the auth audit log index.
 *
 * Every filter is a list rather than a scalar: a single value is the same list of one, so the
 * query layer never branches on which form the caller sent. Absent filters are empty lists,
 * which keeps each `apply()` guard a single emptiness check.
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
     * @param string|null          $search       optional partial email match, never comma-split
     * @param list<AuthAuditEvent> $events       optional audit event filters
     * @param list<int>            $userIds      optional User ID filters
     * @param list<int>            $apiClientIds optional API Client ID filters
     * @param CarbonImmutable|null $from         optional inclusive `created_at` lower bound
     * @param CarbonImmutable|null $to           optional inclusive `created_at` upper bound
     */
    public function __construct(
        public ?string $search = null,
        public array $events = [],
        public array $userIds = [],
        public array $apiClientIds = [],
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
    ) {}
}
