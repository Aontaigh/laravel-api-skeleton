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
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * Maximum length of an attacker-controlled User-Agent stored in audit logs.
     *
     * The header arrives verbatim from the client, so past the cap the value
     * is truncated before persisting - one hostile or absurdly long header
     * cannot widen the row (or the index payload) for every later reader.
    /**
     * Maximum length of an attacker-controlled User-Agent stored in audit logs.
     * The header arrives verbatim from the client, so past the cap the value
     * is truncated before persisting - one hostile or absurdly long header
     * cannot widen the row (or the index payload) for every later reader.
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
            'location_city' => $data->locationCity,
            'location_country' => $data->locationCountry,
            'request_id' => $data->requestId,
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
     *
     * @param  string|null $userAgent the raw User-Agent header
     * @return string|null the capped User-Agent, or null
     */
    private function normalizedUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || mb_strlen($userAgent) <= self::MAX_USER_AGENT_LENGTH) {
            return $userAgent;
        }

        return mb_substr($userAgent, 0, self::MAX_USER_AGENT_LENGTH);
    }
}
