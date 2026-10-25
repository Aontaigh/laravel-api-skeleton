<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Service Stack Parity Gate
|--------------------------------------------------------------------------
|
| Asserts the containers CI runs beside this project are the containers the
| local Sail stack runs, and that every job which installs the lock can satisfy
| it. The dev stack pins a database patch and a cache engine; a CI job resolves
| the same services from a floating tag unless the two are held together, so a
| patch or an engine that moves in one place and not the other passes locally
| and fails in CI, or - worse - passes in CI and fails on a developer machine.
|
| Canonical: `compose.yaml`, which is what a developer runs with `sail up`.
|
| Failing checks:
|
|   1. A `compose.yaml` service image carries no digest. A tag such as
|      `mysql:8.4` or `redis:alpine` moves under the lock file, so two clones
|      of one commit can run different engines and a green suite stops meaning
|      the same thing twice.
|   2. A workflow's `services:` image disagrees with the `compose.yaml` image
|      for the same role - the database, or the cache and queue engine. CI would
|      then exercise a different MySQL patch, or a different cache engine
|      altogether: Redis and Valkey speak the same protocol but are not the same
|      engine, and an RDB written by one is not readable by the other.
|   3. A job that installs composer dependencies does not offer an extension
|      `composer.json` requires with an `ext-*` entry. `composer install`
|      refuses the lock on a missing extension, so the requirement and that
|      job's `extensions:` list have to move together. A job that passes
|      `--ignore-platform-req` is exempt, because it has opted out on purpose.
|
| `--verbose` prints every check it ran, not only the failures.
|
| Usage:
|   php scripts/lint/check-service-parity.php
|   php scripts/lint/check-service-parity.php --verbose
|
| `SAMPLE_API_PARITY_ROOT` points the checks at another tree, which is how a
| fixture drives a boundary case without touching the real files; unset, they
| read the checkout this script lives in.
|--------------------------------------------------------------------------
*/

/*
 * The tree the checks read. `SAMPLE_API_PARITY_ROOT` points them at a fixture instead, which is how a
 * case is driven without touching the real files; unset, they read the checkout this script lives in.
 */
define('REPO_ROOT', getenv('SAMPLE_API_PARITY_ROOT') ?: dirname(__DIR__, 2));

/**
 * Read a repo-relative file.
 *
 * @param  string      $relative the repo-relative path
 * @return string|null the file contents, or null when it is absent or unreadable
 */
function readRepoFile(string $relative): ?string
{
    $path = REPO_ROOT.'/'.$relative;
    $contents = @file_get_contents($path);

    return $contents === false ? null : $contents;
}

/**
 * The role a container image fills, so the dev stack and a workflow can be compared by service
 * rather than by the order their files happen to list. Anything else - a mail catcher, a local
 * cloud emulator - fills no role this gate holds together.
 *
 * @param  string      $image the image reference, or a compose service name
 * @return string|null 'mysql', 'cache', or null when the gate does not track it
 */
function imageRole(string $image): ?string
{
    if (str_contains($image, 'mysql')) {
        return 'mysql';
    }

    if (str_contains($image, 'redis') || str_contains($image, 'valkey')) {
        return 'cache';
    }

    return null;
}

/**
 * Every image the compose file declares, keyed by the role it fills.
 *
 * @param  string                $compose the compose file's contents
 * @return array<string, string> role => image reference
 */
function composeServiceImages(string $compose): array
{
    $images = [];
    $inServices = false;
    $role = null;

    foreach (explode("\n", $compose) as $line) {
        if (preg_match('/^([a-z-]+):\s*$/', $line, $m) === 1) {
            $inServices = $m[1] === 'services';

            continue;
        }

        if (! $inServices) {
            continue;
        }

        if (preg_match('/^ {4}([A-Za-z0-9_.-]+):\s*$/', $line, $m) === 1) {
            $role = imageRole($m[1]);

            continue;
        }

        if ($role !== null && preg_match('/^\s+image:\s*[\'"]?([^\s\'"]+)[\'"]?\s*$/', $line, $m) === 1) {
            $images[$role] = $m[1];
        }
    }

    return $images;
}

/**
 * Every image a workflow declares in a job-level `services:` block, keyed by role. A service
 * declared twice keeps the first; reconciling that is a review's job, not this gate's.
 *
 * @param  string                $workflow the workflow file's contents
 * @return array<string, string> role => image reference
 */
function workflowServiceImages(string $workflow): array
{
    $images = [];
    $servicesIndent = null;
    $role = null;

    foreach (explode("\n", $workflow) as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $indent = strlen($line) - strlen(ltrim($line));

        if ($servicesIndent === null) {
            if (preg_match('/^services:\s*$/', $trimmed) === 1) {
                $servicesIndent = $indent;
            }

            continue;
        }

        if ($indent <= $servicesIndent) {
            $servicesIndent = null;
            $role = null;

            continue;
        }

        if ($indent === $servicesIndent + 4 && preg_match('/^([A-Za-z0-9_.-]+):\s*$/', $trimmed, $m) === 1) {
            $role = imageRole($m[1]);

            continue;
        }

        if ($role !== null && preg_match('/^image:\s*(\S+)\s*$/', $trimmed, $m) === 1) {
            $images[$role] ??= $m[1];
        }
    }

    return $images;
}

/**
 * Every `ext-*` the composer manifest requires, named the way a `setup-php` step installs it.
 *
 * @param  string       $composer the composer manifest's contents
 * @return list<string> the extension names
 */
function requiredExtensions(string $composer): array
{
    preg_match_all('/"ext-([a-z0-9_]+)"\s*:/', $composer, $matches);

    return array_values(array_unique($matches[1]));
}

/**
 * Every job that installs composer dependencies, keyed `<file>:<job>` so a job name repeated
 * across workflows stays distinct, with the extensions that job offers.
 *
 * @param  string                                                         $workflow the workflow file's contents
 * @param  string                                                         $label    the workflow's basename, for the key
 * @return array<string, array{extensions: list<string>, optedOut: bool}> job key => what it offers
 */
function composerJobs(string $workflow, string $label): array
{
    $jobs = [];

    foreach (preg_split('/^(?= {4}[A-Za-z0-9_-]+:\s*$)/m', $workflow) ?: [] as $block) {
        if (! str_contains($block, 'ramsey/composer-install') && ! str_contains($block, 'composer install')) {
            continue;
        }

        if (preg_match('/^ {4}([A-Za-z0-9_-]+):\s*$/m', $block, $m) !== 1) {
            continue;
        }

        $line = preg_match('/^ +extensions:\s*(.+)$/m', $block, $extensions) === 1 ? trim($extensions[1]) : '';

        $jobs[$label.':'.$m[1]] = [
            'extensions' => $line === '' ? [] : array_map(trim(...), explode(',', $line)),
            'optedOut' => str_contains($block, 'ignore-platform-req'),
        ];
    }

    return $jobs;
}

/**
 * The `*.yml` files under `.github/workflows`.
 *
 * @return list<string> absolute paths, sorted
 */
function workflowFiles(): array
{
    $files = glob(REPO_ROOT.'/.github/workflows/*.yml') ?: [];

    sort($files);

    return $files;
}

$verbose = in_array('--verbose', $argv, true);
$checks = [];
$failed = 0;

/**
 * Record one check's outcome.
 *
 * @param  string $label  the check's name
 * @param  bool   $passed whether it passed
 * @param  string $detail the evidence line, or '' when there is nothing to add
 * @return void
 */
function check(string $label, bool $passed, string $detail): void
{
    global $checks, $failed, $verbose;

    $checks[] = [$label, $passed, $detail];

    if (! $passed) {
        $failed++;
    }

    if ($verbose || ! $passed) {
        printf("  [%s] %s\n", $passed ? 'PASS' : 'FAIL', $label);

        if ($detail !== '') {
            printf("         %s\n", $detail);
        }
    }
}

/*
|--------------------------------------------------------------------------
| Containers
|--------------------------------------------------------------------------
|
| The two roles the application actually talks to. The database patch decides
| what the migrations run against; the cache engine decides what the sessions,
| the cache and the queue are stored in, and swapping it is not a no-op.
|
*/

$compose = readRepoFile('compose.yaml');

if ($compose === null) {
    printf("  [FAIL] compose.yaml is readable\n         (not found)\n");
    $failed++;
} else {
    $devImages = composeServiceImages($compose);

    check(
        'compose.yaml pins a database image with a digest',
        str_contains($devImages['mysql'] ?? '', '@sha256:'),
        $devImages['mysql'] ?? '(no mysql service image found)'
    );

    check(
        'compose.yaml pins a cache image with a digest',
        str_contains($devImages['cache'] ?? '', '@sha256:'),
        $devImages['cache'] ?? '(no cache service image found)'
    );

    $workflows = workflowFiles();

    check(
        'there is at least one workflow to compare',
        $workflows !== [],
        $workflows === [] ? '(no .github/workflows/*.yml found)' : sprintf('%d workflow(s)', count($workflows))
    );

    foreach ($workflows as $workflow) {
        $label = basename($workflow);

        foreach (workflowServiceImages(readRepoFile('.github/workflows/'.$label) ?? '') as $role => $image) {
            $expected = $devImages[$role] ?? '';

            check(
                sprintf('%s %s service runs the compose.yaml image', $label, $role),
                $expected !== '' && $image === $expected,
                sprintf('CI %s, compose %s', $image, $expected === '' ? '(no image)' : $expected)
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| PHP Extensions
|--------------------------------------------------------------------------
|
| `composer install` refuses the lock when a required extension is missing, so
| a requirement added to `composer.json` without the matching `extensions:`
| entry fails every job that installs dependencies - including the ones that
| never boot the application. The redis extension is the one that made this
| worth gating: the application speaks to the cache through phpredis, and no
| `ext-*` entry said so.
|
*/

$required = requiredExtensions(readRepoFile('composer.json') ?? '');

check(
    'composer.json declares the extensions the application needs',
    $required !== [],
    $required === [] ? '(none declared: a missing extension surfaces at runtime)' : implode(', ', $required)
);

foreach (workflowFiles() as $workflow) {
    $label = basename($workflow);

    foreach (composerJobs(readRepoFile('.github/workflows/'.$label) ?? '', $label) as $job => $details) {
        if ($details['optedOut']) {
            continue;
        }

        $missing = array_values(array_diff($required, $details['extensions']));

        check(
            sprintf('%s offers every declared extension', $job),
            $missing === [],
            $missing === []
                ? ($details['extensions'] === [] ? '(nothing declared)' : implode(', ', $details['extensions']))
                : 'missing '.implode(', ', $missing)
        );
    }
}

/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

if ($failed === 0) {
    printf("Service Stack Parity Passed (%d Check(s))\n", count($checks));

    exit(0);
}

printf("Service Stack Parity Failed With %d Error(s) Of %d Check(s)\n", $failed, count($checks));

exit(1);
