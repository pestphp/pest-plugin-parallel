<?php

declare(strict_types=1);

namespace Pest\Parallel\Arguments;

use Symfony\Component\Console\Input\ArgvInput;

/**
 * @internal
 */
final class Colors extends Handler
{
    protected function editArgs(): void
    {
        $isDecorated = (new ArgvInput($this->arguments))->getParameterOption('--colors', 'always') !== 'never';

        $this->unsetArgument('--colors');

        if ($isDecorated) {
            $this->setArgument('--colors');
        }
    }
}
