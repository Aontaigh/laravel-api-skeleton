<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

require __DIR__.'/../vendor/autoload.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage Missing Arguments: php scripts/openapi-example-json.php <ExampleName>\n");
    exit(1);
}

/** @var string $exampleName */
$exampleName = $argv[1];

/** @var array<string, mixed> $document */
$document = Yaml::parseFile(__DIR__.'/../docs/openapi.yaml');

/** @var array<string, mixed> $examples */
$examples = $document['components']['examples'];

if (! isset($examples[$exampleName]['value'])) {
    fwrite(STDERR, "OpenAPI Example Not Found: {$exampleName}\n");
    exit(1);
}

echo json_encode($examples[$exampleName]['value'], JSON_THROW_ON_ERROR);
