<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SessionCookieName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for SessionCookieName.
 *
 * Pure string logic, so these run without a database or a live session.
 */
#[CoversClass(SessionCookieName::class)]
final class SessionCookieNameTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Apply the `__Host-` prefix only when the cookie can legally carry it.
     */
    #[Test]
    #[DataProvider('resolveProvider')]
    public function it_applies_the_host_prefix_only_when_the_cookie_is_legal(
        bool $secure,
        ?string $domain,
        string $path,
        string $appName,
        string $expected,
    ): void {
        // Act

        $name = SessionCookieName::resolve($secure, $domain, $path, $appName);

        // Assert

        $this->assertSame($expected, $name);
    }

    /**
     * Keep the configured cookie name legal for the deployment's session flags.
     *
     * `__Host-` is not advisory: a browser drops the cookie outright when the
     * flags do not permit the prefix, so a name that carries it on an insecure
     * or domain-pinned deployment is a sign-in outage with no error. This
     * asserts the invariant across whatever flags the environment supplies,
     * including a deliberate `SESSION_COOKIE` override.
     */
    #[Test]
    public function it_keeps_the_configured_cookie_name_legal_for_the_session_flags(): void
    {
        // Arrange

        $domain = config('session.domain');
        $secure = config()->boolean('session.secure');
        $path = config()->string('session.path');
        $cookie = config()->string('session.cookie');

        // Act

        $prefixIsLegal = $secure
            && ($domain === null || trim(is_string($domain) ? $domain : '') === '')
            && $path === '/';

        // Assert

        if ($prefixIsLegal) {
            $this->assertStringStartsWith(SessionCookieName::HOST_PREFIX, $cookie);

            return;
        }

        $this->assertStringNotContainsString(SessionCookieName::HOST_PREFIX, $cookie);
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Deployment flag combinations and the name each must produce.
     *
     * @return array<string, array{bool, string|null, string, string, string}> the cases
     */
    public static function resolveProvider(): array
    {
        return [
            'secure, host-only, root path' => [true, null, '/', 'Example App', '__Host-example-app-session'],
            'empty domain counts as host-only' => [true, '', '/', 'Example App', '__Host-example-app-session'],
            'domain pinned drops the prefix' => [true, 'example.com', '/', 'Example App', 'example-app-session'],
            'insecure drops the prefix' => [false, null, '/', 'Example App', 'example-app-session'],
            'non-root path drops the prefix' => [true, null, '/app', 'Example App', 'example-app-session'],
            'blank app name falls back' => [true, null, '/', '', '__Host-laravel-session'],
        ];
    }
}
