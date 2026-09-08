<?php

declare(strict_types=1);

namespace App\DataTransferObjects\CspReports;

/**
 * Safe, bounded fields extracted from a browser-submitted CSP violation report.
 *
 * Only known fields are carried across the HTTP boundary - the raw report body
 * (which may include a `script-sample` / `sample` snippet of page content) is
 * never persisted verbatim. See {@see \App\Services\CspReports\CspReportPayloadParser}
 * for the legacy `report-uri` and modern `report-to` field name mapping.
 */
final readonly class CspReportData
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new CspReportData value object.
     *
     * @param string|null $documentUri       the page URL the violation occurred on
     * @param string|null $violatedDirective the CSP directive that was violated
     * @param string|null $blockedUri        the resource the browser refused to load
     * @param string|null $sourceFile        the script or style source that triggered the violation
     * @param int|null    $lineNumber        the line number within `sourceFile`
     * @param string|null $disposition       `enforce` or `report`
     * @param string|null $originalPolicy    the full CSP string in effect (truncated)
     */
    public function __construct(
        public ?string $documentUri = null,
        public ?string $violatedDirective = null,
        public ?string $blockedUri = null,
        public ?string $sourceFile = null,
        public ?int $lineNumber = null,
        public ?string $disposition = null,
        public ?string $originalPolicy = null,
    ) {}
}
