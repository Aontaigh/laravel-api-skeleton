<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Webhooks;

use App\Services\Webhooks\WebhookUrlGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakeWebhookDnsResolver;
use Tests\UnitTestCase;

/**
 * Unit tests for webhook target URL SSRF screening.
 */
#[CoversClass(WebhookUrlGuard::class)]
final class WebhookUrlGuardTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Judge target URL safety against hostile and legitimate inputs.
     *
     * @param string                      $url      the candidate target URL
     * @param array<string, list<string>> $dns      the fake DNS map
     * @param bool                        $expected whether the URL may be registered
     */
    #[Test]
    #[DataProvider('urlSafetyProvider')]
    public function it_judges_target_url_safety(string $url, array $dns, bool $expected): void
    {
        // Arrange

        $guard = new WebhookUrlGuard(new FakeWebhookDnsResolver($dns));

        // Act + Assert

        $this->assertSame($expected, $guard->allows($url));
    }

    /*
    |--------------------------------------------------------------------------
    | Data Providers
    |--------------------------------------------------------------------------
    */

    /**
     * Candidate URLs mapped to whether they may be registered.
     *
     * @return array<string, array{0: string, 1: array<string, list<string>>, 2: bool}>
     */
    public static function urlSafetyProvider(): array
    {
        $public = ['example.com' => ['93.184.216.34']];

        return [
            'public https host' => ['https://example.com/hooks', $public, true],
            'public host with path and query' => ['https://example.com/hooks?a=b', $public, true],
            'public literal IP' => ['https://93.184.216.34/hooks', [], true],
            'http allowed in testing' => ['http://example.com/hooks', $public, true],
            'missing scheme' => ['example.com/hooks', $public, false],
            'ftp scheme' => ['ftp://example.com/hooks', $public, false],
            'embedded credentials' => ['https://user:pass@example.com/hooks', $public, false],
            'private IPv4 literal' => ['https://10.0.0.5/hooks', [], false],
            'loopback literal' => ['https://127.0.0.1/hooks', [], false],
            'link-local metadata IP' => ['http://169.254.169.254/latest', [], false],
            'private hostname' => ['https://internal.example/hooks', ['internal.example' => ['10.0.0.5']], false],
            'unresolvable hostname' => ['https://missing.example/hooks', [], false],
            'not a URL' => ['not a url at all', [], false],
        ];
    }
}
