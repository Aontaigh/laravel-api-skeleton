<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

use App\Models\User;

/**
 * Validated input for issuing a bearer token after successful authentication.
 */
final readonly class FinaliseAuthenticatedSessionData
{
    /**
     * Create a new FinaliseAuthenticatedSessionData value object.
     *
     * @param User        $user              the authenticated User
     * @param string      $deviceName        the Sanctum token display label
     * @param bool        $remember          whether to apply remember-me state
     * @param string      $ipAddress         the caller's IP address for audit
     * @param string|null $userAgent         the caller's user agent for audit
     * @param bool        $regenerateSession whether to rotate the web session id
     */
    public function __construct(
        public User $user,
        public string $deviceName,
        public bool $remember,
        public string $ipAddress,
        public ?string $userAgent,
        public bool $regenerateSession,
    ) {}
}
