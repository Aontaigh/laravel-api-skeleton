<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Runtime Version Parity Gate
|--------------------------------------------------------------------------
|
| Asserts the PHP and Node versions this project pins are declared everywhere
| that can disagree about them, so the local Sail image, a CI job and the
| production host cannot drift apart. A version that moves in one place and not
| the others fails here rather than in a build, a review or a deploy.
|
| Canonical: `.php-version`, which every PHP job reads with `php-version-file`,
| and `.nvmrc`, which every Node job reads with `node-version-file`.
|
| Failing checks:
|
|   1. `composer.json`'s `require.php` floor names a different patch than
|      `.php-version`. The floor is what `composer install` accepts, so a
|      higher one refuses the lock on the version CI installs, and a lower one
|      admits a patch the image is not built against.
|   2. `composer.json`'s `config.platform.php` differs from `.php-version`.
|      That pin is what a local or CI install resolves packages against, so a
|      stale one installs against a PHP the gates never run.
|   3. `docker/8.5/Dockerfile`'s `ARG PHP_VERSION` differs from `.php-version`.
|      That argument is asserted against the installed patch at build time (the
|      Sury PPA is unpinned), so a mismatch bakes a dev image whose PHP is not
|      the PHP the gates run on.
|   4. A `shivammathur/setup-php` step has no `php-version-file: '.php-version'`
|      beside it, which would pin that job to the action's default instead.
|   5. A workflow declares a PHP version of its own that disagrees with
|      `.php-version`: a `PHP_VERSION` environment entry, or a literal
|      `php-version:` input.
|   6. `.nvmrc` names a bare major rather than a full version. The patch is what
|      the dev image installs, so a major alone leaves that image free to move
|      onto a patch no gate ran on.
|   7. `docker/8.5/Dockerfile`'s `ARG NODE_VERSION` differs from `.nvmrc`,
|      patch included. That argument is asserted against the installed version
|      at build time, so a differing patch - or a bare major - bakes a dev image
|      whose Node is not the Node the gates run on.
|   8. `package.json`'s `engines.node` admits no `^A.B.C` alternative on
|      `.nvmrc`'s major, or the floor of that alternative sits above `.nvmrc`.
|      The range is what an install outside CI resolves against, so a floor
|      above the pin refuses the runtime every gate runs, and a range on another
|      major alone claims a support the pin cannot honour.
|   9. An `actions/setup-node` step pins a version this repository does not
|      declare: it has no `node-version-file: .nvmrc`, no matrix reference, and
|      no literal `node-version:` whose major `package.json`'s `engines.node`
|      names. A step left to the action's default, or pinned to an undeclared
|      major, drifts on its own; so does a bare major such as `22`, which
|      resolves to the newest patch on that line.
|
| Checks 4 and 5 replace the upstream script's production-base-image check.
| This repository ships no production image of its own, so there is no
| `Dockerfile` `FROM` line carrying a PHP line to compare; the CI side of the
| parity is that no job holds a PHP version of its own.
|
| The upstream script also gates the production image's Node line, because its
| deploy ships a Node base image. This repository builds no Node image, so
| checks 6 to 8 hold `.nvmrc` against the two places Node is declared instead:
| the dev image's `ARG NODE_VERSION` and `package.json`'s `engines.node` range.
|
| `--verbose` prints every check it ran, not only the failures.
|
| Usage:
|   php scripts/lint/check-php-version-parity.php
|   php scripts/lint/check-php-version-parity.php --verbose
|
| `SAMPLE_API_PARITY_ROOT` points the checks at another tree, which is how a
| fixture drives a boundary case without touching the real workflows; unset,
| they read the checkout this script lives in.
|--------------------------------------------------------------------------
*/

/*
 * The tree the checks read. `SAMPLE_API_PARITY_ROOT` points them at a fixture instead, which is how a
 * case is driven without touching the real workflows; unset, they read the checkout this script
 * lives in.
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
 * The patch line of a version string.
 *
 * @param  string $version the version string
 * @return string the `X.Y.Z` patch line, or '' when it has fewer parts
 */
function patchOf(string $version): string
{
    return preg_match('/^(\d+\.\d+\.\d+)/', trim($version), $matches) === 1 ? $matches[1] : '';
}

/**
 * The major line of a version string.
 *
 * @param  string $version the version string
 * @return string the `X` major line, or '' for anything unparseable
 */
function majorOf(string $version): string
{
    return preg_match('/^(\d+)/', trim($version), $matches) === 1 ? $matches[1] : '';
}

/**
 * The steps a workflow file declares for an action.
 *
 * The step ends at the next list item, so a fixed line window is wrong in both directions: a short
 * step borrows the declaration of the step after it, and a comment naming the key counts as a
 * declaration. Comments are skipped for the same reason - GitHub merges them into the config, this
 * reader does not.
 *
 * @param  string                                              $action the `uses:` reference to look for
 * @return list<array{file: string, line: int, block: string}> the steps, in file order
 */
function workflowSteps(string $action): array
{
    $workflows = glob(REPO_ROOT.'/.github/workflows/*.yml') ?: [];
    $steps = [];

    foreach ($workflows as $workflow) {
        $lines = file($workflow, FILE_IGNORE_NEW_LINES) ?: [];
        $name = basename($workflow);

        foreach ($lines as $index => $line) {
            if (! str_contains($line, 'uses: '.$action.'@')) {
                continue;
            }

            $block = $line;

            foreach (array_slice($lines, $index + 1) as $next) {
                if (preg_match('/^\s*-\s+\S/', $next) === 1) {
                    break;
                }

                if (preg_match('/^\s*#/', $next) !== 1) {
                    $block .= "\n".$next;
                }
            }

            $steps[] = ['file' => $name, 'line' => $index + 1, 'block' => $block];
        }
    }

    return $steps;
}

/**
 * Workflow files whose `setup-php` step is missing its version file.
 *
 * PHP has no equivalent of a matrix leg here, so it needs the version file outright.
 *
 * @param  string       $action      the `uses:` reference to look for
 * @param  string       $versionFile the `with:` key that pins the version
 * @return list<string> the offending steps, as `file:line`
 */
function workflowStepsMissingVersionFile(string $action, string $versionFile): array
{
    $offenders = [];

    foreach (workflowSteps($action) as $step) {
        if (! str_contains($step['block'], $versionFile)) {
            $offenders[] = sprintf('%s:%d', $step['file'], $step['line']);
        }
    }

    return $offenders;
}

/**
 * `setup-node` steps pinned to a version this repository does not declare.
 *
 * A step is pinned when it reads `.nvmrc`, when it takes its version from a matrix reference the
 * workflow declares itself, or when it names a literal version whose major `engines.node` names. A
 * literal on an undeclared major pins nothing, and a bare major such as `22` resolves to the newest
 * patch on that line - the drift this gate exists to catch - so both are reported.
 *
 * @param  list<string> $declaredMajors the majors `package.json`'s `engines.node` names
 * @return list<string> the offending steps, as `file:line`, or `file:line='value'` with the major it names
 */
function setupNodeStepsUnpinned(array $declaredMajors): array
{
    $offenders = [];

    foreach (workflowSteps('actions/setup-node') as $step) {
        if (str_contains($step['block'], 'node-version-file: .nvmrc')) {
            continue;
        }

        if (str_contains($step['block'], 'node-version: ${{ matrix.')) {
            continue;
        }

        $value = preg_match('/^\s*node-version:\s*(.+)$/m', $step['block'], $matches) === 1 ? $matches[1] : '';
        $parts = preg_split('/\s+#/', $value);
        $pin = trim(trim($parts === false ? $value : $parts[0]), "'\"");

        if ($pin !== '' && in_array(majorOf($pin), $declaredMajors, true)) {
            continue;
        }

        $offenders[] = $pin === ''
            ? sprintf('%s:%d', $step['file'], $step['line'])
            : sprintf("%s:%d='%s' (major %s not in engines.node)", $step['file'], $step['line'], $pin, majorOf($pin));
    }

    return $offenders;
}

/**
 * PHP version literals the workflows declare for themselves.
 *
 * A `PHP_VERSION` environment entry and a literal `php-version:` input both pin a job to a version
 * `.php-version` no longer knows about. The supported form, `php-version-file:`, is skipped here,
 * and so is an expression (`${{ ... }}`), because the entry it reads is reported on its own line.
 *
 * @return list<string> the declarations, as `file:line=value`
 */
function workflowPhpVersionDeclarations(): array
{
    $workflows = glob(REPO_ROOT.'/.github/workflows/*.yml') ?: [];
    $declarations = [];

    foreach ($workflows as $workflow) {
        foreach (file($workflow, FILE_IGNORE_NEW_LINES) ?: [] as $index => $line) {
            if (preg_match('/^\s*(?:PHP_VERSION|php-version):\s*(.+)$/', $line, $matches) !== 1) {
                continue;
            }

            $parts = preg_split('/\s+#/', $matches[1]);
            $value = trim($parts === false ? $matches[1] : $parts[0]);
            $value = trim($value, "'\"");

            if ($value === '' || str_starts_with($value, '${{')) {
                continue;
            }

            $declarations[] = sprintf('%s:%d=%s', basename($workflow), $index + 1, $value);
        }
    }

    return $declarations;
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
| PHP
|--------------------------------------------------------------------------
*/

$phpVersion = readRepoFile('.php-version') ?? '';
$phpPatch = patchOf($phpVersion);

if ($phpPatch === '') {
    printf("  [FAIL] .php-version carries a patch version\n         got '%s'\n", trim($phpVersion));
    $failed++;
} else {
    $canonical = trim($phpVersion);

    $composer = readRepoFile('composer.json') ?? '';
    $floor = preg_match('/"php"\s*:\s*"[~^>=]*\s*([0-9][0-9.]*)"/', $composer, $m) === 1 ? $m[1] : '';
    check(
        'composer.json require.php floor matches .php-version',
        $floor !== '' && patchOf($floor) === $phpPatch,
        sprintf('composer floor %s, .php-version %s', $floor === '' ? '(not found)' : $floor, $canonical)
    );

    $platform = preg_match('/"platform"\s*:\s*\{\s*"php"\s*:\s*"([0-9.]+)"/', $composer, $m) === 1 ? $m[1] : '';
    check(
        'composer.json config.platform.php matches .php-version',
        $platform !== '' && patchOf($platform) === $phpPatch,
        sprintf('platform pin %s, .php-version %s', $platform === '' ? '(not found)' : $platform, $canonical)
    );

    $devImage = readRepoFile('docker/8.5/Dockerfile') ?? '';
    $arg = preg_match('/ARG\s+PHP_VERSION=([0-9.]+)/', $devImage, $m) === 1 ? $m[1] : '';
    check(
        'docker/8.5/Dockerfile ARG PHP_VERSION matches .php-version',
        $arg !== '' && patchOf($arg) === $phpPatch,
        sprintf('image floor %s, .php-version %s', $arg === '' ? '(not found)' : $arg, $canonical)
    );

    $missing = workflowStepsMissingVersionFile('shivammathur/setup-php', "php-version-file: '.php-version'");
    check(
        'every setup-php step reads .php-version',
        $missing === [],
        $missing === [] ? '' : 'unversioned step(s): '.implode(', ', $missing)
    );

    $declarations = workflowPhpVersionDeclarations();
    $drifted = array_values(array_filter(
        $declarations,
        static function (string $declaration) use ($phpPatch): bool {
            $value = substr($declaration, (int) strpos($declaration, '=') + 1);

            return patchOf($value) !== $phpPatch;
        }
    ));
    check(
        'no workflow declares a PHP version of its own',
        $drifted === [],
        $drifted === []
            ? sprintf('%d declaration(s) checked against .php-version %s', count($declarations), $canonical)
            : 'declared off the pin: '.implode(', ', $drifted)
    );
}

/*
|--------------------------------------------------------------------------
| Node
|--------------------------------------------------------------------------
|
| `.nvmrc` is the canonical Node version, and unlike PHP its patch is held
| exactly. The dev image installs that version from `ARG NODE_VERSION`, so a
| major-only argument would leave the image free to pick up a patch no gate ran
| on - the drift this section exists to catch.
|
| A `setup-node` step is pinned when it reads `.nvmrc`, when it takes its
| version from a matrix reference the workflow declares itself
| (`node-version: ${{ matrix.node-version }}`), or when it names a literal
| version whose major `engines.node` declares. A literal on an undeclared
| major, or a bare major such as `22` that resolves to the newest patch on that
| line, pins nothing and is reported. PHP has no such case, so its call above
| stays version-file-only.
|
*/

$nodeVersion = trim(readRepoFile('.nvmrc') ?? '');
$nodePatch = patchOf($nodeVersion);
$nodeMajor = majorOf($nodeVersion);

check(
    '.nvmrc carries a full Node version',
    $nodePatch !== '',
    sprintf('.nvmrc %s', $nodeVersion === '' ? '(not found)' : $nodeVersion)
);

$devNodeImage = readRepoFile('docker/8.5/Dockerfile') ?? '';
$nodeArg = preg_match('/ARG\s+NODE_VERSION=([0-9.]+)/', $devNodeImage, $m) === 1 ? $m[1] : '';
check(
    'docker/8.5/Dockerfile ARG NODE_VERSION equals .nvmrc',
    $nodeArg !== '' && $nodeArg === $nodeVersion,
    sprintf('image %s, .nvmrc %s', $nodeArg === '' ? '(not found)' : $nodeArg, $nodeVersion)
);

$package = readRepoFile('package.json') ?? '';
$nodeRange = preg_match('/"node"\s*:\s*"([^"]+)"/', $package, $m) === 1 ? trim($m[1]) : '';
$nodeFloor = '';

foreach (explode('||', $nodeRange) as $alternative) {
    $alternative = trim($alternative);

    if (preg_match('/^\^(\d+\.\d+\.\d+)$/', $alternative, $m) !== 1) {
        continue;
    }

    if (majorOf($m[1]) === $nodeMajor && version_compare($nodeVersion, $m[1], '>=')) {
        $nodeFloor = $m[1];

        break;
    }
}

check(
    'package.json engines.node admits .nvmrc',
    $nodeFloor !== '',
    $nodeFloor !== ''
        ? sprintf('engines range %s admits %s (floor %s)', $nodeRange === '' ? '(not found)' : $nodeRange, $nodeVersion, $nodeFloor)
        : sprintf('engines range %s, .nvmrc %s (no ^%s alternative at or below the pin)', $nodeRange === '' ? '(not found)' : $nodeRange, $nodeVersion, $nodeMajor)
);

$declaredMajors = [];

foreach (explode('||', $nodeRange) as $alternative) {
    if (preg_match('/^[~^>=<\s]*(\d+)/', trim($alternative), $m) === 1) {
        $declaredMajors[] = $m[1];
    }
}

$unpinned = setupNodeStepsUnpinned(array_values(array_unique($declaredMajors)));
check(
    'every setup-node step pins Node from .nvmrc or a version the repo declares',
    $unpinned === [],
    $unpinned === [] ? '' : 'unpinned step(s): '.implode(', ', $unpinned)
);

/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

if ($failed === 0) {
    printf("Runtime Version Parity Passed (%d Check(s))\n", count($checks));

    exit(0);
}

printf("Runtime Version Parity Failed With %d Error(s) Of %d Check(s)\n", $failed, count($checks));

exit(1);
