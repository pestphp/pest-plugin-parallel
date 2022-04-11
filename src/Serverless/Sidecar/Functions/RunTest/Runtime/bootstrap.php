#!/usr/bin/env php
<?php

declare(strict_types=1);

try {
    $lambdaRoot = $_ENV['LAMBDA_TASK_ROOT'];
    require_once "/opt/aws/aws-autoloader.php";
    require_once "{$lambdaRoot}/setup.php";
} catch (Throwable $exception) {
    $exception = json_encode([
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'traceAsString' => $exception->getTraceAsString(),
        'type' => get_class($exception)
    ]);

    echo "\n__START_EXCEPTION__{$exception}__END_EXCEPTION__";
    exit(1);
}

