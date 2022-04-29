<?php

declare(strict_types=1);

namespace Pest\Parallel\Support;

use Illuminate\Foundation\Application;
use Illuminate\Testing\ParallelRunner;
use Orchestra\Testbench\TestCase;

/**
 * @internal
 */
final class Environment
{
    public static function isALaravelApplication(): bool
    {
        return class_exists(Application::class)
            && class_exists(ParallelRunner::class)
            && !class_exists(TestCase::class);
    }
}
