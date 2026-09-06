<?php

declare(strict_types=1);

namespace App\Actions\Sessions;

use App\Contracts\GeoIp\GeoIpLocator;
use App\DataTransferObjects\Sessions\RegisterWebSessionData;
use App\Models\WebSession;
use App\Services\UserAgent\Contracts\UserAgentParser;

/**
 * Records a cookie-bound web session in the per-user registry.
 */
final class RegisterWebSessionAction
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new RegisterWebSessionAction.
     *
     * @param UserAgentParser $userAgentParser parses the user agent into a device label
     * @param GeoIpLocator    $geoIpLocator    resolves city/country from the caller IP
     */
    public function __construct(
        private readonly UserAgentParser $userAgentParser,
        private readonly GeoIpLocator $geoIpLocator,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Create or refresh the registry row for the given Laravel session ID.
     *
     * When the caller did not supply a device label, derive one from the parsed
     * user agent (e.g. `macOS · Chrome`) so the sessions list stays meaningful
     * for plain bearer logins that pass no device name.
     *
     * Geo lookup runs here (once per registration), not on the last-activity
     * heartbeat. Fail-open location fields stay null when the MMDB is missing
     * or the address is private outside `local`.
     *
     * @param  RegisterWebSessionData $data the session registration payload
     * @return WebSession             the persisted registry row
     */
    public function execute(RegisterWebSessionData $data): WebSession
    {
        $location = $this->geoIpLocator->locate($data->ipAddress);

        /** @var WebSession $webSession */
        $webSession = WebSession::query()->updateOrCreate(
            ['session_id' => $data->sessionId],
            [
                'user_id' => $data->user->id,
                'device_name' => $this->deviceName($data),
                'ip_address' => $data->ipAddress,
                'user_agent' => $data->userAgent,
                'remember_me' => $data->rememberMe,
                'location_city' => $location?->city,
                'location_country' => $location?->country,
                'last_activity_at' => now(),
                'revoked_at' => null,
            ],
        );

        return $webSession;
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the device label, deriving it from the user agent when blank.
     *
     * @param  RegisterWebSessionData $data the session registration payload
     * @return string                 the device label to store
     */
    private function deviceName(RegisterWebSessionData $data): string
    {
        $explicit = trim($data->deviceName);

        if ($explicit !== '') {
            return $explicit;
        }

        return $this->userAgentParser->parse($data->userAgent)->deviceLabel();
    }
}
