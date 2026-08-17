<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DataTransferObjects\Auth\TwoFactorStatusOutcome;
use App\Models\User;
use App\Support\ApiDateTime;
use App\Support\Auth\PendingTwoFactor;
use Carbon\CarbonImmutable;

/**
 * Resolves whether a pending two-factor challenge is still active.
 */
final class GetTwoFactorStatusAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the pending challenge status from the session or opaque token.
     *
     * The pending User id comes from the caller's own session or token — never
     * from email input — so a clear expiry response does not create an
     * enumeration vector.
     *
     * @param  string|null            $token the opaque pending token, when supplied by the client
     * @return TwoFactorStatusOutcome the active challenge or a client-safe failure
     */
    public function execute(?string $token): TwoFactorStatusOutcome
    {
        $pending = PendingTwoFactor::resolve($token);
        $user = $pending === null ? null : User::query()->find($pending->userId);

        if (! $user instanceof User) {
            return TwoFactorStatusOutcome::sessionExpired();
        }

        if ($user->isSuspended()) {
            return TwoFactorStatusOutcome::accountSuspended();
        }

        $expiresAt = PendingTwoFactor::expiresAt($token);

        if ($expiresAt === null) {
            return TwoFactorStatusOutcome::sessionExpired();
        }

        $serialisedExpiry = ApiDateTime::serialize(
            CarbonImmutable::createFromTimestampUTC($expiresAt),
        );

        if ($serialisedExpiry === null) {
            return TwoFactorStatusOutcome::sessionExpired();
        }

        return TwoFactorStatusOutcome::success($serialisedExpiry);
    }
}
