#!/usr/bin/env php
<?php

declare(strict_types=1);

$payload = json_decode($_SERVER['argv'][1], true);

$localCwd = $payload['localCwd'];
$tempFile = $payload['tempFile'];
$testCommand = $payload['testCommand'];

/**
 * We need to replace all reference to the user's local project with
 * the location of the project on lambda.
 */
$testCommand = array_map(function (string $commandPart) use ($localCwd, $tempFile) {
    return str_replace($localCwd, '/tmp/project', $commandPart);
}, $testCommand);

echo join(' ', $testCommand);
