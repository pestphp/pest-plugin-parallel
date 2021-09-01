<?php

declare(strict_types=1);

namespace Pest\Parallel\Arguments;

use Pest\Parallel\Paratest\Runner;

/**
 * @internal
 */
final class Parallel extends Handler
{
    protected function editArgs(): void
    {
        $this->unsetArgument('--parallel')
            ->unsetArgument('-p')
            ->setArgument('--runner', Runner::class);
    }
}
