<?php

declare(strict_types=1);

use Pest\Parallel\Serverless\Sidecar\Functions\RunTest\Runtime\Support\Utils;

$payload = Utils::payload();

$commands = array_map(function (array $testDetails) use ($payload) {
    $command = str_replace(
        $payload['localCwd'],
        '/tmp/project',
        join(' ', $testDetails['testCommand'])
    );

    return ['command' => $command, 'tempFile' => $testDetails['tempFile']];
}, $payload['tests']);

$results = [];

foreach ($commands as $test) {
    $output = [];
    $exitCode = 0;

    exec("cd /tmp/project; {$test['command']}", $output, $exitCode);

    $results[] = [
        'output' => $output,
        'code' => intval($exitCode),
        'junit' => file_get_contents($test['tempFile'])
    ];
}

echo json_encode($results);
