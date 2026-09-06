<?php

declare(strict_types=1);

namespace App\DataTransferObjects\SystemHealth;

use App\Enums\SystemHealthStatus;

/**
 * The outcome of a single component's health probe, ready to persist as one
 * {@see \App\Models\SystemHealthCheck} row.
 */
final readonly class SystemHealthCheckResult
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** Maximum characters persisted for a message, matching the `message` column. */
    public const int MAX_MESSAGE_LENGTH = 255;

    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /**
     * A Title Case, human-readable explanation - never a stack trace or secret.
     *
     * Only the caught exception's `getMessage()` is ever passed in here, never
     * `getTraceAsString()`, so a probe failure cannot leak file paths or line
     * numbers into the `system_health_checks.message` column, which the public
     * status endpoint eventually serves to anonymous callers.
     */
    public ?string $message;

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new SystemHealthCheckResult value object.
     *
     * @param SystemHealthStatus $status         the outcome of the probe
     * @param int|null           $responseTimeMs how long the probe took, when measurable
     * @param string|null        $message        a human-readable explanation; bounded to MAX_MESSAGE_LENGTH
     */
    public function __construct(
        public SystemHealthStatus $status,
        public ?int $responseTimeMs = null,
        ?string $message = null,
    ) {
        /*
         * Bounded here, at the single persistence boundary, rather than in
         * every concrete check: no caller can later smuggle an unbounded
         * upstream message past the `message` column's 255-character cap.
         */
        $this->message = $message === null ? null : mb_substr($message, 0, self::MAX_MESSAGE_LENGTH);
    }
}
