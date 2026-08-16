<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

use App\Enums\AuthAuditEvent;

/**
 * Payload for recording an authentication audit event.
 */
final readonly class RecordAuthAuditData
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new RecordAuthAuditData value object.
     *
     * @param AuthAuditEvent $event                 the authentication event type
     * @param int|null       $userId                the authenticated User id when known
     * @param string|null    $email                 the email address attempted or used
     * @param string|null    $ipAddress             the client IP address
     * @param string|null    $userAgent             the client user agent
     * @param int|null       $personalAccessTokenId the issued Sanctum token id when applicable
     * @param int|null       $apiClientId           the API client id for client-credentials exchange
     * @param bool           $rememberMe            whether remember-me was requested
     */
    public function __construct(
        public AuthAuditEvent $event,
        public ?int $userId = null,
        public ?string $email = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?int $personalAccessTokenId = null,
        public ?int $apiClientId = null,
        public bool $rememberMe = false,
    ) {}
}
