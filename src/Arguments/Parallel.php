<?php

declare(strict_types=1);

namespace Pest\Parallel\Arguments;

use Pest\Parallel\Concerns\Arguments\ManagesArguments;
use Pest\Parallel\Contracts\ArgumentHandler;
use Pest\Parallel\Paratest\Runner;

/**
 * @internal
 */
final class Parallel implements ArgumentHandler
{
    use ManagesArguments;

    private function editArguments(): void
    {
        $this->unsetArgument('--parallel')
            ->unsetArgument('-p');

        if (!$this->hasArgument('--runner')) {
            $this->setArgument('--runner', Runner::class);
        }
    }
}
