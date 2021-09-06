<?php

declare(strict_types=1);

namespace Pest\Parallel\Support;

/**
 * @internal
 */
final class ProcessEnvironmentHandler
{
    /**
     * @return array<string, int>
     */
    public function getTokens(): array
    {
        $env = $this->default();

        if (Environment::isALaravelApplication()) {
            $env = array_merge($env, $this->laravel());
        }

        return $env;
    }

    /**
     * @return array<string, int>
     */
    private function default(): array
    {
        return ['COLLISION_FORCE_COLORS' => 1];
    }

    /**
     * @return array<string, int>
     */
    private function laravel(): array
    {
        return ['LARAVEL_PARALLEL_TESTING' => 1];
    }
}
