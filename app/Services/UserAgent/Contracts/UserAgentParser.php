<?php

declare(strict_types=1);

namespace App\Services\UserAgent\Contracts;

use App\DataTransferObjects\Auth\ParsedUserAgent;

/**
 * Parses a raw user-agent header into a structured, library-agnostic DTO.
 */
interface UserAgentParser
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Parse a user-agent string.
     *
     * Implementations must never throw: a null, empty, or unparseable agent
     * resolves to an all-null "unknown" DTO so callers never branch on errors.
     *
     * @param  string|null     $userAgent the raw user-agent header value
     * @return ParsedUserAgent the parsed result, or the unknown DTO
     */
    public function parse(?string $userAgent): ParsedUserAgent;
}
