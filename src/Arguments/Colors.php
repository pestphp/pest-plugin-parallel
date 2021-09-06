<?php

declare(strict_types=1);

namespace Pest\Parallel\Arguments;

use Pest\Parallel\Concerns\Arguments\ManagesArguments;
use Pest\Parallel\Contracts\ArgumentHandler;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * @internal
 */
final class Colors implements ArgumentHandler
{
    use ManagesArguments;

    private function editArguments(): void
    {
        $isDecorated = (new ArgvInput($this->arguments))->getParameterOption('--colors', 'always') !== 'never';

        $this->unsetArgument('--colors');

        if ($isDecorated) {
            $this->setArgument('--colors');
        }
    }
}
