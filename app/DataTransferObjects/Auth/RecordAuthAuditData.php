<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

use App\DataTransferObjects\GeoIp\GeoIpLocation;
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
     * @param int|null       $userId                the authenticated User ID when known
     * @param string|null    $email                 the email address attempted or used
     * @param string|null    $ipAddress             the client IP address
     * @param string|null    $userAgent             the client user agent
     * @param int|null       $personalAccessTokenId the issued Sanctum token ID when applicable
     * @param int|null       $apiClientId           the API client ID for client-credentials exchange
     * @param bool           $rememberMe            whether remember-me was requested
     * @param string|null    $locationCity          the city resolved from the IP at persist time
     * @param string|null    $locationCountry       the ISO 3166-1 alpha-2 country resolved at persist time
     * @param string|null    $requestId             the request correlation ID for joining logs and responses
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
        public ?string $locationCity = null,
        public ?string $locationCountry = null,
        public ?string $requestId = null,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return a copy carrying the location resolved for the recorded IP.
     *
     * The queued audit listener resolves the location from the event payload
     * (`ipAddress` captured at dispatch) and hands it to the persistence
     * Action through this copy, so the Action never touches `request()`.
     *
     * @param  GeoIpLocation|null $location the resolved city and country, or null
     * @return self               the copy carrying the resolved location
     */
    public function withLocation(?GeoIpLocation $location): self
    {
        return new self(
            event: $this->event,
            userId: $this->userId,
            email: $this->email,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
            personalAccessTokenId: $this->personalAccessTokenId,
            apiClientId: $this->apiClientId,
            rememberMe: $this->rememberMe,
            locationCity: $location?->city,
            locationCountry: $location?->country,
            requestId: $this->requestId,
        );
    }
}
