<?php

declare(strict_types=1);

namespace App\Services\UserAgent;

use App\DataTransferObjects\Auth\ParsedUserAgent;
use App\Services\UserAgent\Contracts\UserAgentParser;

/**
 * Regex-based user-agent parser covering the common desktop and mobile agents.
 *
 * Deliberately dependency-free: the WhichBrowser package is not installed, and
 * introducing one for a best-effort device label would add a heavy dependency
 * for a cosmetic field. Anything it cannot classify resolves individual fields
 * to null rather than guessing, and an entirely unparseable agent produces the
 * unknown DTO so callers never see an exception.
 */
final class BasicUserAgentParser implements UserAgentParser
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * Cap the attacker-controlled user-agent before parsing or storing.
     */
    private const int MAX_LENGTH = 1024;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Parse the user-agent, deriving browser, platform, and device type.
     *
     * @param  string|null     $userAgent the raw user-agent header value
     * @return ParsedUserAgent the parsed result, or the unknown DTO
     */
    public function parse(?string $userAgent): ParsedUserAgent
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return ParsedUserAgent::unknown();
        }

        $agent = mb_substr(trim($userAgent), 0, self::MAX_LENGTH);

        [$browser, $version] = $this->browser($agent);

        return new ParsedUserAgent(
            browser: $browser,
            browserVersion: $version,
            platform: $this->platform($agent),
            deviceType: $this->deviceType($agent),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Detect the browser name and version.
     *
     * Order matters: Edge carries a Chrome token and Chrome carries a Safari
     * token, so the more specific families are matched first.
     *
     * @param  string                        $agent the user-agent string
     * @return array{0: ?string, 1: ?string} the browser name and version, or nulls
     */
    private function browser(string $agent): array
    {
        $browsers = [
            'Edge' => '/Edg(?:e|A|iOS)?\/(\d+(?:\.\d+)*)/',
            'Opera' => '/(?:OPR|Opera)\/(\d+(?:\.\d+)*)/',
            'Chrome' => '/Chrome\/(\d+(?:\.\d+)*)/',
            'Firefox' => '/Firefox\/(\d+(?:\.\d+)*)/',
            'Safari' => '/Version\/(\d+(?:\.\d+)*).*Safari\//',
        ];

        foreach ($browsers as $name => $pattern) {
            if (preg_match($pattern, $agent, $matches) === 1) {
                return [$name, $matches[1]];
            }
        }

        return [null, null];
    }

    /**
     * Detect the operating system / platform.
     *
     * @param  string      $agent the user-agent string
     * @return string|null the platform, or null when unknown
     */
    private function platform(string $agent): ?string
    {
        if (str_contains($agent, 'Windows')) {
            return 'Windows';
        }

        if (str_contains($agent, 'iPhone') || str_contains($agent, 'iPad')) {
            return 'iOS';
        }

        if (str_contains($agent, 'Android')) {
            return 'Android';
        }

        if (str_contains($agent, 'Mac OS X') || str_contains($agent, 'Macintosh')) {
            return 'macOS';
        }

        if (str_contains($agent, 'Linux')) {
            return 'Linux';
        }

        return null;
    }

    /**
     * Classify the device as mobile (handset) or desktop.
     *
     * @param  string      $agent the user-agent string
     * @return string|null the device type, or null when unknown
     */
    private function deviceType(string $agent): ?string
    {
        if (str_contains($agent, 'Mobile') || str_contains($agent, 'iPhone') || str_contains($agent, 'Android')) {
            return 'mobile';
        }

        if (str_contains($agent, 'iPad')) {
            return 'tablet';
        }

        if (str_contains($agent, 'Windows') || str_contains($agent, 'Macintosh') || str_contains($agent, 'Linux')) {
            return 'desktop';
        }

        return null;
    }
}
