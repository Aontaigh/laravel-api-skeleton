<?php

declare(strict_types=1);

namespace Tests\Feature\Http\WellKnown;

use App\Http\Controllers\WellKnown\ShowSecurityTxtController;
use App\Http\Requests\WellKnown\ShowSecurityTxtRequest;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the public RFC 9116 security.txt route.
 */
#[CoversClass(ShowSecurityTxtController::class)]
#[CoversClass(ShowSecurityTxtRequest::class)]
final class ShowSecurityTxtControllerTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Serve the committed security.txt file as plain text.
     */
    #[Test]
    public function it_serves_the_security_txt_file(): void
    {
        // Act

        $response = $this->get('/.well-known/security.txt');

        // Assert

        $response->assertOk();
        $response->assertHeader('content-type', 'text/plain; charset=utf-8');

        $body = $response->streamedContent();

        $this->assertStringContainsString(
            'Contact: https://github.com/Aontaigh/laravel-api-skeleton/security/advisories/new',
            $body,
        );
        $this->assertStringContainsString(
            'Policy: https://github.com/Aontaigh/laravel-api-skeleton/blob/main/SECURITY.md',
            $body,
        );
    }

    /**
     * Fail the build when Expires is missing, in the past, or more than a year away.
     *
     * RFC 9116 requires Expires and recommends a horizon under one year so the
     * file cannot silently go stale after a mailbox or policy change.
     */
    #[Test]
    public function it_keeps_the_expires_field_current(): void
    {
        // Act

        $response = $this->get('/.well-known/security.txt');

        // Assert

        $response->assertOk();

        $body = $response->streamedContent();
        $matched = preg_match('/^Expires:\s*(\S+)/m', $body, $matches);

        $this->assertSame(1, $matched, 'security.txt must include an Expires field');

        $expiresAt = new DateTimeImmutable($matches[1]);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $oneYearFromNow = $now->modify('+366 days');

        $this->assertGreaterThan($now, $expiresAt, 'security.txt Expires must be in the future');
        $this->assertLessThanOrEqual($oneYearFromNow, $expiresAt, 'security.txt Expires must be within a year');
    }

    /**
     * Answer 404 when the configured file is missing, not 500.
     */
    #[Test]
    public function it_returns_not_found_when_the_file_is_missing(): void
    {
        // Arrange

        config(['security.security_txt' => 'public/.well-known/does-not-exist.txt']);

        // Act

        $response = $this->get('/.well-known/security.txt');

        // Assert

        $response->assertNotFound();
    }
}
