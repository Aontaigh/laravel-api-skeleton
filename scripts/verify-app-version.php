<?php

declare(strict_types=1);

/**
 * Ensure composer.json, OpenAPI, and optional git tags share one app version.
 *
 * Usage: php scripts/verify-app-version.php
 */
$root = dirname(__DIR__);

/** @var array<string, mixed>|null $composer */
$composer = json_decode((string) file_get_contents($root.'/composer.json'), true);
$version = $composer['version'] ?? null;

if (! is_string($version) || $version === '') {
    fwrite(STDERR, "composer.json must define a non-empty \"version\" field.\n");

    exit(1);
}

$openapiPath = $root.'/docs/openapi.yaml';
$openapi = (string) file_get_contents($openapiPath);

if (! preg_match('/^info:\R  title:.*\R  version: (\S+)/m', $openapi, $infoMatch)) {
    fwrite(STDERR, "Could not read info.version from docs/openapi.yaml.\n");

    exit(1);
}

if ($infoMatch[1] !== $version) {
    fwrite(
        STDERR,
        "docs/openapi.yaml info.version is {$infoMatch[1]}; expected {$version} from composer.json.\n",
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
        "docs/openapi.yaml must reference {$version} in the HealthSuccess example and HealthData schema.\n",
    );

    exit(1);
}

$tag = trim((string) getenv('GITHUB_REF_NAME'));
if ($tag !== '' && str_starts_with($tag, 'v')) {
    $tagVersion = ltrim($tag, 'v');
    if ($tagVersion !== $version) {
        fwrite(
            STDERR,
            "Git tag {$tag} resolves to {$tagVersion}; composer.json version is {$version}.\n",
        );

        exit(1);
    }
}

fwrite(STDOUT, "App version {$version} is in sync.\n");
