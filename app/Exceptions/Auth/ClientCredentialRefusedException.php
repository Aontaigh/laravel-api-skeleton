<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use App\Enums\ClientIneligibilityReason;
use App\Models\ApiClient;
use App\Models\User;
use RuntimeException;

/**
 * A verified client credential the application then declines on policy grounds.
 *
 * The distinction this type carries is the one every major identity provider draws:
 * once the secret has verified, the credential proved out, and everything after that
 * is an authorisation decision rather than an authentication failure. Microsoft Entra
 * separates the two in its sign-in logs, AWS CloudTrail reports `AccessDenied`
 * separately from `InvalidClientTokenId`, and this repository's `AuditOutcome` enum
 * names the second case `Refused` for exactly this reason.
 *
 * Without it, a suspended service account and a mistyped secret produce audit rows
 * that differ only in their event name, so an incident responder cannot tell a
 * deliberate policy decline from a credential attack. The HTTP response stays
 * generic either way - this never reaches the caller as a distinguishable error.
 *
 * Diagnostic copy follows php-quality (CLI and Diagnostic Errors): Title Case
 * headlines, no trailing full stop; detail after a colon when needed.
 */
final class ClientCredentialRefusedException extends RuntimeException
{
    /**
     * @param ClientIneligibilityReason $reason the rule the client failed, persisted on the audit row
     * @param ApiClient|null            $client the authenticated client, when one resolved
     * @param User|null                 $owner  the owning principal, when it is known
     */
    public function __construct(
        public readonly ClientIneligibilityReason $reason,
        public readonly ?ApiClient $client = null,
        public readonly ?User $owner = null,
    ) {
        parent::__construct('Client Credential Refused: '.$reason->value);
    }
}
