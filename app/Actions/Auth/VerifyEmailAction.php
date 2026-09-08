<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Log;

/**
 * Verifies a User's e-mail address from the temporary signed link.
 */
final class VerifyEmailAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Consume a signed verification link and mark the e-mail verified.
     *
     * Laravel's MustVerifyEmail contract uses `sha1(email)` as a checksum
     * beside the HMAC signature so the signed URL binds to that mailbox.
     * Integrity comes from the signature - a rewritten hash without a valid
     * signature never reaches this compare - so `sha1` here is an ownership
     * binding, not a secret digest.
     *
     * Verification is idempotent: an already-verified User returns success
     * without re-marking. A missing User or a foreign-mailbox hash returns
     * failure and records the attempt for the SOC - the HTTP response never
     * distinguishes the cause.
     *
     * @example
     * app(VerifyEmailAction::class)->execute($id, $hash, $ip, $agent, $requestId);
     *
     * @param  string      $id        the User ID from the signed URL
     * @param  string      $hash      the e-mail hash from the signed URL
     * @param  string|null $ipAddress the caller IP captured at the HTTP boundary
     * @param  string|null $userAgent the caller User-Agent captured at the boundary
     * @param  string|null $requestId the request correlation ID captured at the boundary
     * @return bool        true when the mailbox is (or already was) verified
     */
    public function execute(string $id, string $hash, ?string $ipAddress, ?string $userAgent, ?string $requestId = null): bool
    {
        $user = User::query()->find($id);

        // nosemgrep: php.lang.security.weak-crypto.weak-crypto
        if (! $user instanceof User || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            AuthEventOccurred::dispatch(new RecordAuthAuditData(
                event: AuthAuditEvent::EmailVerificationFailed,
                userId: $user?->id,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                requestId: $requestId,
            ));

            Log::warning('E-Mail Verification Link Rejected', [
                'user_id' => $user?->id,
            ]);

            return false;
        }

        if ($user->hasVerifiedEmail()) {
            return true;
        }

        $user->markEmailAsVerified();
        event(new Verified($user));
        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::EmailVerified,
            userId: $user->id,
            email: $user->email,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            requestId: $requestId,
        ));

        return true;
    }
}
