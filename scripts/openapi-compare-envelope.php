<?php

declare(strict_types=1);

if ($argc < 4) {
    fwrite(STDERR, "Usage: php scripts/openapi-compare-envelope.php <name> <expectedJson> <actualJson>\n");
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
            return $object === [] ? [] : [keys_shape($object[0])];
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

/** @var list<string> $errors */
$errors = [];

if (keys_shape($expected) !== keys_shape($actual)) {
    $errors[] = 'shape mismatch'
        ."\n  expected: ".json_encode(keys_shape($expected), JSON_THROW_ON_ERROR)
        ."\n  actual:   ".json_encode(keys_shape($actual), JSON_THROW_ON_ERROR);
}

foreach (['status', 'status_code', 'message'] as $key) {
    if (($expected[$key] ?? null) !== ($actual[$key] ?? null)) {
        $errors[] = "{$key}: expected ".json_encode($expected[$key] ?? null)
            .', got '.json_encode($actual[$key] ?? null);
    }
}

if ($errors !== []) {
    fwrite(STDERR, "FAIL {$name}\n");

    foreach ($errors as $error) {
        fwrite(STDERR, "  - {$error}\n");
    }

    exit(1);
}

echo "OK   {$name}\n";
