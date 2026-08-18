<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Sessions;

use App\Models\User;

/**
 * Validated input for registering a cookie-bound web session.
 */
final readonly class RegisterWebSessionData
{
    /**
     * Create a new RegisterWebSessionData value object.
     *
     * @param User        $user       the authenticated User
     * @param string      $sessionId  the Laravel session id stamped at the privilege boundary
     * @param string      $deviceName the device label from the login request
     * @param string      $ipAddress  the caller IP address
     * @param string|null $userAgent  the caller user agent
     * @param bool        $rememberMe whether remember-me is active for this session
     */
    public function __construct(
        public User $user,
        public string $sessionId,
        public string $deviceName,
        public string $ipAddress,
        public ?string $userAgent,
        public bool $rememberMe,
    ) {}
}
