#!/usr/bin/env php
<?php

declare(strict_types=1);

try {
    $lambdaRoot = $_ENV['LAMBDA_TASK_ROOT'];

    // We need AWS to allow us to access S3
    require_once "/opt/aws/aws-autoloader.php";

    // Our Utils class will allow our scripts to access common functions
    require_once $lambdaRoot . '/Support/Utils.php';

    // Finally, we'll load our script
    require_once $lambdaRoot . '/' . $_SERVER['argv'][2];
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

