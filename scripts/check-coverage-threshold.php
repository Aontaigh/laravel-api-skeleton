<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Coverage Threshold Gate
|--------------------------------------------------------------------------
|
| Parses the PHPUnit Clover XML written by `composer test:coverage`
| (storage/coverage/clover.xml) and exits non-zero when the project's
| overall line-coverage percentage drops below the threshold supplied
| as the first CLI argument.
|
| Diagnostic copy follows php-quality (CLI and Diagnostic Errors): Title Case
| headlines, no trailing full stop; detail after a colon when needed.
|
| Usage (locally and in CI):
|
|     composer test:coverage
|     php scripts/check-coverage-threshold.php 90
|
| The two metrics we compare are
|
|     covered statements / statements * 100
|
| read from the `<project>/<metrics>` element. Statements are the unit
| PHPUnit counts as "lines of executable code", so the ratio matches
| the percentage rendered by `php artisan test --coverage`.
|
| When run inside GitHub Actions the script also appends a Markdown
| summary block to `$GITHUB_STEP_SUMMARY` so the result is rendered
| directly on the workflow run page.
*/

$threshold = (float) ($argv[1] ?? '0');

if ($threshold <= 0.0 || $threshold > 100.0) {
    fwrite(STDERR, "Coverage Threshold Must Be a Number Between 0 and 100\n");

    exit(2);
}

$cloverPath = __DIR__.'/../storage/coverage/clover.xml';

if (! is_file($cloverPath)) {
    fwrite(STDERR, "Clover Report Not Found: run `composer test:coverage` first (no file at {$cloverPath})\n");

    exit(2);
}

$xml = @simplexml_load_file($cloverPath);

if ($xml === false) {
    fwrite(STDERR, "Failed To Load Clover Report: could not parse XML at {$cloverPath}\n");

    exit(2);
}

$metrics = $xml->project->metrics ?? null;

if ($metrics === null) {
    fwrite(STDERR, "Failed To Read Clover Report: missing `project/metrics` element\n");

    exit(2);
}

$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];

if ($statements <= 0) {
    fwrite(STDERR, "Failed To Read Clover Report: zero statements reported\n");

    exit(2);
}

$coverage = ($covered / $statements) * 100.0;
$passed = $coverage + 0.0001 >= $threshold;

printf("Line Coverage: %.2f%% (%d / %d Statements)\n", $coverage, $covered, $statements);
printf("Threshold:     %.2f%%\n", $threshold);

writeJobSummary($xml, $coverage, $threshold, $covered, $statements, $passed);

if (! $passed) {
    fwrite(STDERR, sprintf(
        "Coverage Below Threshold: %.2f%% is below required %.2f%%\n",
        $coverage,
        $threshold,
    ));

    exit(1);
}

echo "Coverage Gate Passed\n";
exit(0);

/**
 * Append a Markdown summary block to `$GITHUB_STEP_SUMMARY` so the coverage
 * result renders directly on the GitHub Actions run page.
 *
 * Outside Actions (`GITHUB_STEP_SUMMARY` unset, e.g. local runs), this is a
 * no-op — the operator already sees the same numbers on stdout.
 */
function writeJobSummary(
    SimpleXMLElement $xml,
    float $coverage,
    float $threshold,
    int $covered,
    int $statements,
    bool $passed,
): void {
    $summaryPath = getenv('GITHUB_STEP_SUMMARY');

    if ($summaryPath === false || $summaryPath === '') {
        return;
    }

    $verdict = $passed ? 'PASS' : 'FAIL';
    $delta = $coverage - $threshold;
    $deltaSign = $delta >= 0 ? '+' : '';
    $bar = renderProgressBar($coverage);

    $lines = [];
    $lines[] = '## PHP Coverage Report';
    $lines[] = '';
    $lines[] = '| Metric | Value |';
    $lines[] = '| --- | --- |';
    $lines[] = sprintf('| **Line Coverage** | %.2f%% |', $coverage);
    $lines[] = sprintf('| Statements Covered | %s / %s |', number_format($covered), number_format($statements));
    $lines[] = sprintf('| Threshold | %.2f%% |', $threshold);
    $lines[] = sprintf('| Delta | %s%.2f%% |', $deltaSign, $delta);
    $lines[] = sprintf('| Verdict | **%s** |', $verdict);
    $lines[] = '';
    $lines[] = '`'.$bar.'`';
    $lines[] = '';

    $worst = collectLowestCoverageFiles($xml, limit: 10);

    if ($worst !== []) {
        $lines[] = '<details><summary>Lowest-Coverage Files (Top 10)</summary>';
        $lines[] = '';
        $lines[] = '| File | Coverage | Covered / Total |';
        $lines[] = '| --- | ---: | ---: |';

        foreach ($worst as $row) {
            $lines[] = sprintf(
                '| `%s` | %.2f%% | %d / %d |',
                $row['name'],
                $row['percent'],
                $row['covered'],
                $row['statements'],
            );
        }

        $lines[] = '';
        $lines[] = '</details>';
        $lines[] = '';
    }

    file_put_contents($summaryPath, implode("\n", $lines), FILE_APPEND);
}

/**
 * Render a 30-cell progress bar so the headline number reads as a
 * proportion at a glance even when collapsed in a long PR view.
 */
function renderProgressBar(float $coverage): string
{
    $cells = 30;
    $filled = (int) round(($coverage / 100.0) * $cells);
    $filled = max(0, min($cells, $filled));

    return sprintf(
        '[%s%s] %.2f%%',
        str_repeat('#', $filled),
        str_repeat('-', $cells - $filled),
        $coverage,
    );
}

/**
 * Walk every `<file>` in the Clover report, compute line coverage
 * percentage, and return the bottom `$limit` files (lowest first).
 *
 * Files with zero statements are excluded — they are typically pure
 * interfaces / enums where coverage is undefined and would otherwise
 * crowd the table with `0/0` noise.
 *
 * @return list<array{name: string, percent: float, covered: int, statements: int}>
 */
function collectLowestCoverageFiles(SimpleXMLElement $xml, int $limit): array
{
    $rows = [];
    $repoRoot = realpath(__DIR__.'/..') ?: '';

    foreach ($xml->xpath('//file') ?? [] as $file) {
        $metrics = $file->metrics ?? null;

        if ($metrics === null) {
            continue;
        }

        $statements = (int) $metrics['statements'];
        $covered = (int) $metrics['coveredstatements'];

        if ($statements <= 0) {
            continue;
        }

        $name = (string) $file['name'];

        if ($repoRoot !== '' && str_starts_with($name, $repoRoot.DIRECTORY_SEPARATOR)) {
            $name = substr($name, strlen($repoRoot) + 1);
        }

        $rows[] = [
            'name' => $name,
            'percent' => ($covered / $statements) * 100.0,
            'covered' => $covered,
            'statements' => $statements,
        ];
    }

    usort($rows, static fn (array $a, array $b): int => $a['percent'] <=> $b['percent']);

    return array_slice($rows, 0, $limit);
}
