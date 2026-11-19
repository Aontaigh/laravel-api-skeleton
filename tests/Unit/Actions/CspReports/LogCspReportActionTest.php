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

    /**
     * Cap the number of warnings one request can write to the channel.
     *
     * The 16 KiB body cap still allows thousands of tiny reports; without a
     * report cap one anonymous request could flood the daily channel. The
     * excess reports are dropped and the truncation is logged once.
     */
    #[Test]
    public function it_caps_the_number_of_reports_logged_per_batch(): void
    {
        // Arrange

        $reports = array_map(
            static fn (int $index): CspReportData => new CspReportData(documentUri: "https://example.com/{$index}"),
            range(1, 17),
        );

        Log::shouldReceive('channel')->times(17)->with('csp-reports')->andReturnSelf();
        Log::shouldReceive('warning')
            ->times(16)
            ->with('CSP Violation Reported', Mockery::type('array'));
        Log::shouldReceive('warning')
            ->once()
            ->with('CSP Reports Truncated', Mockery::on(
                static fn (array $context): bool => ($context['logged'] ?? null) === 16
                    && ($context['received'] ?? null) === 17,
            ));

        // Act

        (new LogCspReportAction)->execute($reports);

        // Assert

        // Mockery expectations above are the assertion.
    }
}
