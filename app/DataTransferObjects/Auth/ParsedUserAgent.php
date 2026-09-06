<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

/**
 * A parsed user-agent, normalised across parser implementations.
 */
final readonly class ParsedUserAgent
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new ParsedUserAgent value object.
     *
     * @param string|null $browser        the detected browser name (e.g. Chrome)
     * @param string|null $browserVersion the detected browser version
     * @param string|null $platform       the detected operating system / platform
     * @param string|null $deviceType     the detected device type (e.g. desktop, mobile)
     */
    public function __construct(
        public ?string $browser = null,
        public ?string $browserVersion = null,
        public ?string $platform = null,
        public ?string $deviceType = null,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Build an all-null "unknown" instance for missing or unparseable agents.
     *
     * @return self the unknown parsed agent
     */
    public static function unknown(): self
    {
        return new self;
    }

    /**
     * Build the persisted device label `{platform} · {browser}`.
     *
     * Truncated to 255 characters to match the registry column. Missing fields
     * fall back to Unknown so the label is always non-empty.
     *
     * @return string the device label
     */
    public function deviceLabel(): string
    {
        $platform = $this->platform ?? 'Unknown';
        $browser = $this->browser ?? 'Unknown';

        return mb_substr($platform.' · '.$browser, 0, 255);
    }
}
