<?php

declare(strict_types=1);

/**
 * Compare a live API response to an OpenAPI example envelope.
 *
 * Diagnostic copy follows php-quality (CLI and Diagnostic Errors): Title Case
 * headlines, no trailing full stop; detail after a colon when needed.
 */
if ($argc < 4) {
    fwrite(STDERR, "Usage Missing Arguments: php scripts/openapi-compare-envelope.php <name> <expectedJson> <actualJson>\n");
    exit(1);
}

/** @var string $name */
$name = $argv[1];

/** @var array<string, mixed> $expected */
$expected = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);

/** @var array<string, mixed> $actual */
$actual = json_decode($argv[3], true, 512, JSON_THROW_ON_ERROR);

/**
 * @param  mixed $object the JSON value to summarise
 * @return mixed nested key shapes or scalar type name
 */
function keys_shape(mixed $object): mixed
{
    if (is_array($object)) {
        if (array_is_list($object)) {
            /*
             * Keep every list item's shape so shapes_compatible can reject a
             * response where only index 0 matches the documented item.
             */
            return array_map(keys_shape(...), $object);
        }

        $shape = [];

        foreach ($object as $key => $value) {
            $shape[$key] = keys_shape($value);
        }

        ksort($shape);

        return $shape;
    }

    return get_debug_type($object);
}

/**
 * Whether two key shapes are compatible.
 *
 * List shapes compare every actual item against the documented (first)
 * expected item shape - a valid first row must not hide a broken later row.
 * Leaves compare exactly, so a documented `null` only matches `null`.
 *
 * @param  mixed $expectedShape keys_shape() of the OpenAPI example
 * @param  mixed $actualShape   keys_shape() of the live response
 * @return bool  true when shapes align
 */
function shapes_compatible(mixed $expectedShape, mixed $actualShape): bool
{
    if (! is_array($expectedShape) || ! is_array($actualShape)) {
        return $expectedShape === $actualShape;
    }

    $expectedIsList = array_is_list($expectedShape);
    $actualIsList = array_is_list($actualShape);

    if ($expectedIsList !== $actualIsList) {
        return false;
    }

    if ($expectedIsList) {
        if ($expectedShape === [] || $actualShape === []) {
            return $expectedShape === $actualShape;
        }

        $expectedItem = $expectedShape[0];

        foreach ($actualShape as $actualItemShape) {
            if (! shapes_compatible($expectedItem, $actualItemShape)) {
                return false;
            }
        }

        return true;
    }

    if (array_keys($expectedShape) !== array_keys($actualShape)) {
        return false;
    }

    foreach ($expectedShape as $key => $expectedValue) {
        if (! shapes_compatible($expectedValue, $actualShape[$key])) {
            return false;
        }
    }

    return true;
}

/** @var list<string> $errors */
$errors = [];

if (! shapes_compatible(keys_shape($expected), keys_shape($actual))) {
    $errors[] = 'Envelope Shape Mismatch'
        ."\n  expected: ".json_encode(keys_shape($expected), JSON_THROW_ON_ERROR)
        ."\n  actual:   ".json_encode(keys_shape($actual), JSON_THROW_ON_ERROR);
}

foreach (['status', 'status_code', 'message'] as $key) {
    if (($expected[$key] ?? null) !== ($actual[$key] ?? null)) {
        $errors[] = "Envelope Field Mismatch ({$key}): expected ".json_encode($expected[$key] ?? null)
            .', got '.json_encode($actual[$key] ?? null);
    }
}

if ($errors !== []) {
    fwrite(STDERR, "OpenAPI Example Failed: {$name}\n");

    foreach ($errors as $error) {
        fwrite(STDERR, "  - {$error}\n");
    }

    exit(1);
}

echo "OK   {$name}\n";
