#!/usr/bin/env php
<?php

declare(strict_types=1);

$payload = json_decode($_SERVER['argv'][3], true);

echo json_encode([
    'output' => $_SERVER['argv'][1],
    'code' => intval($_SERVER['argv'][2]),
    'junit' => file_get_contents($payload['tempFile']),
]);
