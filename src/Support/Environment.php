<?php

declare(strict_types=1);

namespace Pest\Parallel\Support;

use Illuminate\Testing\ParallelRunner;

/**
 * @internal
 */
final class Environment
{
    public static function isALaravelApplication(): bool
    {
        return class_exists('\App\Http\Kernel') && class_exists(ParallelRunner::class);
    }
}
