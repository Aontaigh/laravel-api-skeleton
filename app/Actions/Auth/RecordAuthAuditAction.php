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
            'user_agent' => $data->userAgent,
            'personal_access_token_id' => $data->personalAccessTokenId,
            'api_client_id' => $data->apiClientId,
            'remember_me' => $data->rememberMe,
        ]);

        return $log;
    }
}
