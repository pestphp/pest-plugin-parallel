<?php

declare(strict_types=1);

namespace Pest\Parallel\Support;

use Composer\InstalledVersions;

/**
 * @internal
 */
final class Environment
{
    public static function isALaravelApplication(): bool
    {
        return InstalledVersions::isInstalled('laravel/framework') && ! InstalledVersions::isInstalled('orchestra/testbench');
    }
}
