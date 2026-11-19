<?php

declare(strict_types=1);

namespace App\Actions\CspReports;

use App\DataTransferObjects\CspReports\CspReportData;
use Illuminate\Support\Facades\Log;

/**
 * Writes CSP violation reports to the dedicated `csp-reports` log channel.
 */
final class LogCspReportAction
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * Maximum number of reports logged from a single request.
     *
     * The 16 KiB body cap still allows thousands of tiny reports; capping the
     * count keeps one anonymous request from flooding the daily channel.
     *
     * @var int
     */
    private const int MAX_REPORTS_PER_BATCH = 16;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Log every report in the batch, one line each.
     *
     * A single browser POST can carry more than one report (the Reporting
     * API batches queued reports into one request), so each is logged
     * individually rather than as one combined line - each violation stays
     * independently greppable. Reports past the cap are dropped and the
     * truncation is logged once.
     *
     * @param  list<CspReportData> $reports the parsed, bounded reports to log
     * @return void
     */
    public function execute(array $reports): void
    {
        $received = count($reports);
        $logged = 0;

        foreach ($reports as $report) {
            if ($logged >= self::MAX_REPORTS_PER_BATCH) {
                break;
            }

            Log::channel('csp-reports')->warning('CSP Violation Reported', [
                'document_uri' => $report->documentUri,
                'violated_directive' => $report->violatedDirective,
                'blocked_uri' => $report->blockedUri,
                'source_file' => $report->sourceFile,
                'line_number' => $report->lineNumber,
                'disposition' => $report->disposition,
                'original_policy' => $report->originalPolicy,
            ]);

            $logged++;
        }

        if ($received > self::MAX_REPORTS_PER_BATCH) {
            Log::channel('csp-reports')->warning('CSP Reports Truncated', [
                'logged' => $logged,
                'received' => $received,
            ]);
        }
    }
}
