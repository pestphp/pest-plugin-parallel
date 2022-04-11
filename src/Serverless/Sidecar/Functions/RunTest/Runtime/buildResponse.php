#!/usr/bin/env php
<?php

declare(strict_types=1);

echo json_encode([
    'output' => $_SERVER['argv'][1],
    'code' => $_SERVER['argv'][2],
]);
