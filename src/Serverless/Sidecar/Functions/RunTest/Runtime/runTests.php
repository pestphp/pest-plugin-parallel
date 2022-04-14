<?php

declare(strict_types=1);

use Pest\Parallel\Serverless\Sidecar\Functions\RunTest\Runtime\Support\Utils;

$payload = Utils::payload();

$timeWhenStarting = microtime(true);

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
    if (Utils::timeoutExpired($timeWhenStarting)) {
        $results[] = null;
        break;
    }

    $output = [];
    $exitCode = 0;

    $command = "cd /tmp/project; /opt/bin/php {$test['command']}";

    exec($command, $output, $exitCode);

    $results[] = [
        'output' => $output,
        'code' => intval($exitCode),
        'junit' => file_get_contents($test['tempFile']),
        'debug' => [
            'command' => $command,
        ],
    ];
}

echo json_encode($results);
