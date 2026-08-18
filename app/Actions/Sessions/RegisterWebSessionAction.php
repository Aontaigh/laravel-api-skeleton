<?php

declare(strict_types=1);

namespace App\Actions\Sessions;

use App\DataTransferObjects\Sessions\RegisterWebSessionData;
use App\Models\WebSession;

/**
 * Records a cookie-bound web session in the per-user registry.
 */
final class RegisterWebSessionAction
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Create or refresh the registry row for the given Laravel session id.
     *
     * @param  RegisterWebSessionData $data the session registration payload
     * @return WebSession             the persisted registry row
     */
    public function execute(RegisterWebSessionData $data): WebSession
    {
        /** @var WebSession $webSession */
        $webSession = WebSession::query()->updateOrCreate(
            ['session_id' => $data->sessionId],
            [
                'user_id' => $data->user->id,
                'device_name' => $data->deviceName,
                'ip_address' => $data->ipAddress,
                'user_agent' => $data->userAgent,
                'remember_me' => $data->rememberMe,
                'last_activity_at' => now(),
                'revoked_at' => null,
            ],
        );

        return $webSession;
    }
}
