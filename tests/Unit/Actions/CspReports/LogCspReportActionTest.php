<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\CspReports;

use App\Actions\CspReports\LogCspReportAction;
use App\DataTransferObjects\CspReports\CspReportData;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for LogCspReportAction.
 */
#[CoversClass(LogCspReportAction::class)]
final class LogCspReportActionTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Write one warning line per report to the dedicated `csp-reports` channel.
     */
    #[Test]
    public function it_logs_each_report_to_the_csp_reports_channel(): void
    {
        // Arrange

        $report = new CspReportData(
            documentUri: 'https://example.com/page',
            violatedDirective: 'script-src',
            blockedUri: 'https://evil.example/x.js',
            sourceFile: 'https://example.com/app.js',
            lineNumber: 42,
            disposition: 'enforce',
            originalPolicy: "default-src 'self'",
        );

        Log::shouldReceive('channel')
            ->once()
            ->with('csp-reports')
            ->andReturnSelf();

        Log::shouldReceive('warning')
            ->once()
            ->with('CSP Violation Reported', Mockery::on(static function (array $context) use ($report): bool {
                return $context['document_uri'] === $report->documentUri
                    && $context['violated_directive'] === $report->violatedDirective
                    && $context['blocked_uri'] === $report->blockedUri
                    && $context['source_file'] === $report->sourceFile
                    && $context['line_number'] === $report->lineNumber
                    && $context['disposition'] === $report->disposition
                    && $context['original_policy'] === $report->originalPolicy;
            }));

        // Act

        (new LogCspReportAction)->execute([$report]);

        // Assert

        // Mockery expectations above (`once()`) are the assertion.
    }

    /**
     * Log one line per report when a batch contains more than one.
     */
    #[Test]
    public function it_logs_a_separate_line_for_each_report_in_a_batch(): void
    {
        // Arrange

        Log::shouldReceive('channel')->twice()->with('csp-reports')->andReturnSelf();
        Log::shouldReceive('warning')->twice()->with('CSP Violation Reported', Mockery::type('array'));

        // Act

        (new LogCspReportAction)->execute([
            new CspReportData(documentUri: 'https://example.com/one'),
            new CspReportData(documentUri: 'https://example.com/two'),
        ]);

        // Assert

        // Mockery expectations above (`twice()`) are the assertion.
    }

    /**
     * Write nothing when the batch is empty.
     */
    #[Test]
    public function it_writes_nothing_for_an_empty_batch(): void
    {
        // Arrange

        Log::shouldReceive('channel')->never();

        // Act

        (new LogCspReportAction)->execute([]);

        // Assert

        // Mockery expectation above (`never()`) is the assertion.
    }
}
