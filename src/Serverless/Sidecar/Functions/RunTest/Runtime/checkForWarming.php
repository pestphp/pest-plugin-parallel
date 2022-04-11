#!/usr/bin/env php
<?php

declare(strict_types=1);

$payload = json_decode($_SERVER['argv'][1], true);

echo ($payload['warming'] ?? false) ? 1 : 0;
