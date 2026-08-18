<?php

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage Missing Arguments: php scripts/openapi-merge-created-token.php <expectedJson> <liveJson>\n");
    exit(1);
}

/** @var array<string, mixed> $expected */
$expected = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);

/** @var array<string, mixed> $live */
$live = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);

/** @var array<string, mixed> $expectedToken */
$expectedToken = $expected['data']['token'];

/** @var array<string, mixed> $liveToken */
$liveToken = $live['data']['token'];

$liveToken['id'] = $expectedToken['id'];
$liveToken['created_at'] = $expectedToken['created_at'];
$liveToken['expires_at'] = $expectedToken['expires_at'];
$live['data']['plain_text_token'] = $expected['data']['plain_text_token'];
$live['data']['token'] = $liveToken;

echo json_encode($live, JSON_THROW_ON_ERROR);
