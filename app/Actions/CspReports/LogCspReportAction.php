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
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Log every report in the batch, one line each.
     *
     * A single browser POST can carry more than one report (the Reporting
     * API batches queued reports into one request), so each is logged
     * individually rather than as one combined line - each violation stays
     * independently greppable.
     *
     * @param  list<CspReportData> $reports the parsed, bounded reports to log
     * @return void
     */
    public function execute(array $reports): void
    {
        foreach ($reports as $report) {
            Log::channel('csp-reports')->warning('CSP Violation Reported', [
                'document_uri' => $report->documentUri,
                'violated_directive' => $report->violatedDirective,
                'blocked_uri' => $report->blockedUri,
                'source_file' => $report->sourceFile,
                'line_number' => $report->lineNumber,
                'disposition' => $report->disposition,
                'original_policy' => $report->originalPolicy,
            ]);
        }
    }
}
