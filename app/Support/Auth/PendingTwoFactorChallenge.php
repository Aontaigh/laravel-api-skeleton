<?php

declare(strict_types=1);

namespace App\Support\Auth;

/**
 * Resolved pending two-factor state between credential and verification steps.
 */
final readonly class PendingTwoFactorChallenge
{
    /**
     * Create a new PendingTwoFactorChallenge value object.
     *
     * @param int         $userId         the User awaiting verification
     * @param bool        $shouldRemember whether remember-me was requested
     * @param string|null $deviceName     the device label submitted at login/register
     * @param string|null $token          the opaque cache token for stateless clients
     */
    public function __construct(
        public int $userId,
        public bool $shouldRemember,
        public ?string $deviceName,
        public ?string $token,
    ) {}
}
