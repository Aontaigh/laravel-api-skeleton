<?php

declare(strict_types=1);

namespace Tests\Unit\Services\CspReports;

use App\Services\CspReports\CspReportPayloadParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for the CSP report body parser.
 *
 * Pure string-in, DTO-out cases: no database, no HTTP, no log channel.
 */
#[CoversClass(CspReportPayloadParser::class)]
final class CspReportPayloadParserTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Extract fields from a legacy `report-uri` body.
     */
    #[Test]
    public function it_extracts_fields_from_a_legacy_csp_report_body(): void
    {
        // Arrange

        $parser = new CspReportPayloadParser;

        $body = (string) json_encode([
            'csp-report' => [
                'document-uri' => 'https://example.com/page',
                'violated-directive' => 'script-src',
                'blocked-uri' => 'https://evil.example/x.js',
                'source-file' => 'https://example.com/app.js',
                'line-number' => 42,
                'disposition' => 'enforce',
                'original-policy' => "default-src 'self'",
            ],
        ]);

        // Act

        $reports = $parser->parse('application/csp-report', $body);

        // Assert

        $this->assertCount(1, $reports);
        $this->assertSame('https://example.com/page', $reports[0]->documentUri);
        $this->assertSame('script-src', $reports[0]->violatedDirective);
        $this->assertSame('https://evil.example/x.js', $reports[0]->blockedUri);
        $this->assertSame('https://example.com/app.js', $reports[0]->sourceFile);
        $this->assertSame(42, $reports[0]->lineNumber);
        $this->assertSame('enforce', $reports[0]->disposition);
        $this->assertSame("default-src 'self'", $reports[0]->originalPolicy);
    }

    /**
     * Extract fields from a modern Reporting API array body.
     */
    #[Test]
    public function it_extracts_fields_from_a_reports_plus_json_array(): void
    {
        // Arrange

        $parser = new CspReportPayloadParser;

        $body = (string) json_encode([
            [
                'type' => 'csp-violation',
                'url' => 'https://example.com/page',
                'body' => [
                    'documentURL' => 'https://example.com/page',
                    'effectiveDirective' => 'script-src',
                    'blockedURL' => 'https://evil.example/x.js',
                    'disposition' => 'enforce',
                ],
            ],
            [
                'type' => 'csp-violation',
                'url' => 'https://example.com/other',
                'body' => [
                    'documentURL' => 'https://example.com/other',
                    'effectiveDirective' => 'img-src',
                    'blockedURL' => 'https://evil.example/y.png',
                    'disposition' => 'report',
                ],
            ],
        ]);

        // Act

        $reports = $parser->parse('application/reports+json', $body);

        // Assert

        $this->assertCount(2, $reports);
        $this->assertSame('https://example.com/page', $reports[0]->documentUri);
        $this->assertSame('script-src', $reports[0]->violatedDirective);
        $this->assertSame('https://example.com/other', $reports[1]->documentUri);
        $this->assertSame('img-src', $reports[1]->violatedDirective);
    }

    /**
     * Return an empty list for malformed JSON instead of throwing.
     */
    #[Test]
    public function it_returns_an_empty_list_for_malformed_json(): void
    {
        // Arrange

        $parser = new CspReportPayloadParser;

        // Act

        $reports = $parser->parse('application/csp-report', '{not valid json');

        // Assert

        $this->assertSame([], $reports);
    }

    /**
     * Return an empty list when the legacy key is missing.
     */
    #[Test]
    public function it_returns_an_empty_list_when_the_csp_report_key_is_missing(): void
    {
        // Arrange

        $parser = new CspReportPayloadParser;

        // Act

        $reports = $parser->parse('application/csp-report', '{"unrelated":"shape"}');

        // Assert

        $this->assertSame([], $reports);
    }

    /**
     * Truncate an oversized field before it reaches the log line.
     */
    #[Test]
    public function it_truncates_an_oversized_field(): void
    {
        // Arrange

        $parser = new CspReportPayloadParser;

        $body = (string) json_encode([
            'csp-report' => ['document-uri' => str_repeat('a', 5000)],
        ]);

        // Act

        $reports = $parser->parse('application/csp-report', $body);

        // Assert

        $this->assertCount(1, $reports);
        $this->assertSame(2048, mb_strlen((string) $reports[0]->documentUri));
    }

    /**
     * Flag a body that exceeds the max decode size.
     */
    #[Test]
    public function it_flags_a_body_that_exceeds_the_max_size(): void
    {
        // Arrange

        $parser = new CspReportPayloadParser;

        // Act + Assert

        $this->assertFalse($parser->exceedsMaxBodySize('{"csp-report":{}}'));
        $this->assertTrue($parser->exceedsMaxBodySize(str_repeat('a', (16 * 1024) + 1)));
    }
}
