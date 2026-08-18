<?php

declare(strict_types=1);

/**
 * Ensure composer.json, OpenAPI, and optional git tags share one app version.
 *
 * Diagnostic copy follows php-quality (CLI and Diagnostic Errors): Title Case
 * headlines, no trailing full stop; detail after a colon when needed.
 *
 * Usage: php scripts/verify-app-version.php
 */
$root = dirname(__DIR__);

/** @var array<string, mixed>|null $composer */
$composer = json_decode((string) file_get_contents($root.'/composer.json'), true);
$version = $composer['version'] ?? null;

if (! is_string($version) || $version === '') {
    fwrite(STDERR, "Composer Version Missing: composer.json must define a non-empty \"version\" field\n");

    exit(1);
}

$openapiPath = $root.'/docs/openapi.yaml';
$openapi = (string) file_get_contents($openapiPath);

if (! preg_match('/^info:\R  title:.*\R  version: (\S+)/m', $openapi, $infoMatch)) {
    fwrite(STDERR, "OpenAPI Version Missing: could not read info.version from docs/openapi.yaml\n");

    exit(1);
}

if ($infoMatch[1] !== $version) {
    fwrite(
        STDERR,
        "OpenAPI Version Mismatch: docs/openapi.yaml info.version is {$infoMatch[1]}; expected {$version} from composer.json\n",
    );

    exit(1);
}

$healthExampleCount = preg_match_all(
    '/^\s+version: '.preg_quote($version, '/').'\s*$/m',
    $openapi,
);

if ($healthExampleCount < 2) {
    fwrite(
        STDERR,
        "OpenAPI Health Version Missing: docs/openapi.yaml must reference {$version} in the HealthSuccess example and HealthData schema\n",
    );

    exit(1);
}

$tag = trim((string) getenv('GITHUB_REF_NAME'));
if ($tag !== '' && str_starts_with($tag, 'v')) {
    $tagVersion = ltrim($tag, 'v');
    if ($tagVersion !== $version) {
        fwrite(
            STDERR,
            "Git Tag Version Mismatch: tag {$tag} resolves to {$tagVersion}; composer.json version is {$version}\n",
        );

        exit(1);
    }
}

fwrite(STDOUT, "App Version {$version} Is in Sync\n");
