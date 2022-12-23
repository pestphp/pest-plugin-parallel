<?php

declare(strict_types=1);

namespace Pest\Parallel\Support;


/**
 * @internal
 */
final class Environment
{
    public static function isALaravelApplication(): bool
    {
        return class_exists(\Illuminate\Foundation\Application::class)
            && class_exists(\Illuminate\Testing\ParallelRunner::class)
            && !class_exists(\Orchestra\Testbench\TestCase::class);
    }
}
