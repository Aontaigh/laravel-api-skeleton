<?php

declare(strict_types=1);

namespace Tests\Unit\Services\UserAgent;

use App\DataTransferObjects\Auth\ParsedUserAgent;
use App\Services\UserAgent\BasicUserAgentParser;
use App\Services\UserAgent\Contracts\UserAgentParser;
use App\Services\UserAgent\NullUserAgentParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for the user-agent parser contract and drivers.
 */
#[CoversClass(BasicUserAgentParser::class)]
#[CoversClass(NullUserAgentParser::class)]
#[CoversClass(ParsedUserAgent::class)]
final class UserAgentParserTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /*
     * Parsing Tests
     * -------------
     */

    /**
     * Parse a real Chrome desktop agent into browser, platform, and device type.
     */
    #[Test]
    public function it_parses_a_real_chrome_desktop_agent(): void
    {
        // Arrange

        $parser = app(UserAgentParser::class);

        // Act

        $parsed = $parser->parse('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36');

        // Assert

        $this->assertSame('Chrome', $parsed->browser);
        $this->assertSame('126.0.0.0', $parsed->browserVersion);
        $this->assertSame('macOS', $parsed->platform);
        $this->assertSame('desktop', $parsed->deviceType);
    }

    /*
     * Device Label Tests
     * ------------------
     */

    /**
     * Resolve null and blank agents to the unknown DTO without throwing.
     */
    #[Test]
    public function it_returns_unknown_for_null_and_blank_agents(): void
    {
        // Arrange

        $parser = app(UserAgentParser::class);

        // Act

        $fromNull = $parser->parse(null);
        $fromBlank = $parser->parse('   ');

        // Assert

        $this->assertNull($fromNull->browser);
        $this->assertNull($fromNull->platform);
        $this->assertNull($fromBlank->browser);
        $this->assertNull($fromBlank->platform);
    }

    /**
     * Resolve a garbage agent without throwing.
     */
    #[Test]
    public function it_returns_unknown_for_a_garbage_agent(): void
    {
        // Arrange

        $parser = app(UserAgentParser::class);

        // Act

        $parsed = $parser->parse('###not a user agent###');

        // Assert

        $this->assertNull($parsed->browser);
    }

    /**
     * Cap an oversized agent before parsing.
     */
    #[Test]
    public function it_caps_an_oversized_agent_without_throwing(): void
    {
        // Arrange

        $parser = app(UserAgentParser::class);

        // Act

        $parsed = $parser->parse(str_repeat('Chrome/126.0 ', 400));

        // Assert: a 5000-char agent is parsed without throwing

        $this->assertSame('Chrome', $parsed->browser);
    }

    /**
     * Build the persisted device label.
     */
    #[Test]
    public function it_builds_a_device_label(): void
    {
        // Arrange

        $parsed = new ParsedUserAgent(browser: 'Chrome', platform: 'macOS');

        // Act

        $label = $parsed->deviceLabel();

        // Assert

        $this->assertSame('macOS · Chrome', $label);
    }

    /*
     * Container Tests
     * ---------------
     */

    /**
     * Resolve the contract through the container to a working parser.
     */
    #[Test]
    public function it_resolves_the_contract_via_the_container(): void
    {
        // Act

        $parsed = app(UserAgentParser::class)->parse('Mozilla/5.0 (X11; Linux x86_64) Firefox/130.0');

        // Assert - a contract-typed resolution parses a real agent

        $this->assertSame('Firefox', $parsed->browser);
    }
}
