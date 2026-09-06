<?php

declare(strict_types=1);

namespace App\Services\UserAgent;

use App\DataTransferObjects\Auth\ParsedUserAgent;
use App\Services\UserAgent\Contracts\UserAgentParser;

/**
 * The trivial parser that always resolves to an unknown DTO.
 *
 * Serves as the fallback driver and an easy test double: swapping the config
 * driver to `null` disables parsing entirely without touching call sites.
 */
final class NullUserAgentParser implements UserAgentParser
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Return the unknown DTO for any input.
     *
     * @param  string|null     $userAgent ignored
     * @return ParsedUserAgent always the unknown DTO
     */
    public function parse(?string $userAgent): ParsedUserAgent
    {
        return ParsedUserAgent::unknown();
    }
}
