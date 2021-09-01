<?php

declare(strict_types=1);

namespace Pest\Parallel\Contracts;

/**
 * @internal
 */
interface ArgumentHandler
{
    /**
     * @param array<int, string> $arguments
     *
     * @return array<int, string>
     */
    public function handle(array $arguments): array;
}
