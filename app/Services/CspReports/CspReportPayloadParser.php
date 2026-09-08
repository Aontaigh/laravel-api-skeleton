<?php

declare(strict_types=1);

namespace App\Services\CspReports;

use App\DataTransferObjects\CspReports\CspReportData;

/**
 * Parses a raw CSP violation report body into safe, bounded DTOs.
 *
 * Browsers POST two incompatible shapes depending on which reporting
 * mechanism fired: the legacy `report-uri` directive sends
 * `Content-Type: application/csp-report` with a single `{"csp-report": {...}}`
 * object using hyphenated keys; the modern `report-to` / Reporting API
 * directive sends `Content-Type: application/reports+json` with a JSON array
 * of `{type, url, body}` report objects using camelCase keys inside `body`.
 * Both are normalised to the same {@see CspReportData} shape.
 */
final class CspReportPayloadParser
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /**
     * Reject the body before `json_decode` runs at all past this size.
     *
     * A CSP violation report is a handful of short fields; 16 KiB is
     * generous headroom while still stopping an anonymous, unauthenticated
     * caller from using this endpoint to burn CPU on `json_decode` or fill
     * disk via the `csp-reports` log channel.
     */
    private const int MAX_BODY_BYTES = 16 * 1024;

    /** Cap every extracted string field before it reaches the log line. */
    private const int MAX_FIELD_LENGTH = 2048;

    /** `Content-Type` sent by the modern Reporting API (`report-to`). */
    private const string REPORTING_API_CONTENT_TYPE = 'application/reports+json';

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the raw body exceeds the size this endpoint will decode.
     *
     * @param  string $rawBody the raw request body
     * @return bool   true when the body is too large to parse
     */
    public function exceedsMaxBodySize(string $rawBody): bool
    {
        return strlen($rawBody) > self::MAX_BODY_BYTES;
    }

    /**
     * Parse a raw CSP report body into a list of safe DTOs.
     *
     * Malformed or empty JSON, and bodies that do not match either known
     * shape, resolve to an empty list rather than throwing - the caller
     * always responds success either way (a browser discards the response
     * body and never retries a report).
     *
     * @param  string              $contentType the request's `Content-Type` header
     * @param  string              $rawBody     the raw request body
     * @return list<CspReportData> the extracted reports, empty when none could be read
     */
    public function parse(string $contentType, string $rawBody): array
    {
        $decoded = json_decode($rawBody, true);

        if (! is_array($decoded)) {
            return [];
        }

        $bodies = str_contains($contentType, self::REPORTING_API_CONTENT_TYPE)
            ? $this->extractReportingApiBodies($decoded)
            : $this->extractLegacyBody($decoded);

        return array_map($this->toCspReportData(...), $bodies);
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Extract each report's `body` object from a Reporting API array payload.
     *
     * @param  array<mixed>       $decoded the decoded JSON array
     * @return list<array<mixed>> the extracted report bodies
     */
    private function extractReportingApiBodies(array $decoded): array
    {
        $bodies = [];

        foreach ($decoded as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            /*
             * Some implementations send the report body directly at the top
             * level rather than nested under `body`; fall back to the entry
             * itself so both shapes still extract fields.
             */
            $body = is_array($entry['body'] ?? null) ? $entry['body'] : $entry;
            $bodies[] = $body;
        }

        return $bodies;
    }

    /**
     * Extract the single `csp-report` object from a legacy `report-uri` payload.
     *
     * @param  array<mixed>       $decoded the decoded JSON object
     * @return list<array<mixed>> zero or one report bodies
     */
    private function extractLegacyBody(array $decoded): array
    {
        $body = $decoded['csp-report'] ?? null;

        return is_array($body) ? [$body] : [];
    }

    /**
     * Map a decoded report body onto the safe, bounded DTO fields.
     *
     * @param  array<mixed>  $body the decoded report body (legacy or Reporting API shape)
     * @return CspReportData the normalised, bounded report
     */
    private function toCspReportData(array $body): CspReportData
    {
        return new CspReportData(
            documentUri: $this->stringField($body, ['document-uri', 'documentURL', 'document_uri']),
            violatedDirective: $this->stringField($body, ['violated-directive', 'effectiveDirective', 'effective-directive', 'effective_directive']),
            blockedUri: $this->stringField($body, ['blocked-uri', 'blockedURL', 'blocked_url']),
            sourceFile: $this->stringField($body, ['source-file', 'sourceFile']),
            lineNumber: $this->intField($body, ['line-number', 'lineNumber']),
            disposition: $this->stringField($body, ['disposition']),
            originalPolicy: $this->stringField($body, ['original-policy', 'originalPolicy']),
        );
    }

    /**
     * Read the first present, non-empty string value across candidate keys.
     *
     * @param  array<mixed> $body the decoded report body
     * @param  list<string> $keys candidate key names, tried in order
     * @return string|null  the bounded string value, or null when absent
     */
    private function stringField(array $body, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $body[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return mb_substr($value, 0, self::MAX_FIELD_LENGTH);
            }
        }

        return null;
    }

    /**
     * Read the first present integer-ish value across candidate keys.
     *
     * @param  array<mixed> $body the decoded report body
     * @param  list<string> $keys candidate key names, tried in order
     * @return int|null     the value as an int, or null when absent
     */
    private function intField(array $body, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = $body[$key] ?? null;

            if (is_int($value)) {
                return $value;
            }

            if (is_string($value) && is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }
}
