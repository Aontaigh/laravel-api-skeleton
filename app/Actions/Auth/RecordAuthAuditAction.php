<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Models\AuthAuditLog;

/**
 * Persists an authentication audit event.
 */
final class RecordAuthAuditAction
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /**
     * The maximum length of an attacker-controlled User-Agent stored in audit logs.
     */
    private const MAX_USER_AGENT_LENGTH = 1024;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Record the given authentication event.
     *
     * @example
     * app(RecordAuthAuditAction::class)->execute($data);
     *
     * @param  RecordAuthAuditData $data the audit payload
     * @return AuthAuditLog        the persisted audit row
     */
    public function execute(RecordAuthAuditData $data): AuthAuditLog
    {
        /** @var AuthAuditLog $log */
        $log = AuthAuditLog::query()->create([
            'user_id' => $data->userId,
            'event' => $data->event,
            'email' => $data->email,
            'ip_address' => $data->ipAddress,
            'user_agent' => $this->normalizedUserAgent($data->userAgent),
            'personal_access_token_id' => $data->personalAccessTokenId,
            'api_client_id' => $data->apiClientId,
            'remember_me' => $data->rememberMe,
        ]);

        return $log;
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Cap attacker-controlled request metadata before the audit row is persisted.
     */
    private function normalizedUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || mb_strlen($userAgent) <= self::MAX_USER_AGENT_LENGTH) {
            return $userAgent;
        }

        return mb_substr($userAgent, 0, self::MAX_USER_AGENT_LENGTH);
    }
}
