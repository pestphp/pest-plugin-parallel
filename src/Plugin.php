<?php

declare(strict_types=1);

namespace Pest\Parallel;

use Pest\Actions\LoadStructure;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Parallel\Paratest\Runner;
use Pest\TestSuite;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * @internal
 */
final class Plugin implements HandlesArguments
{
    public function handleArguments(array $arguments): array
    {
        if (!in_array('--parallel', $arguments)) {
            return $arguments;
        }

        LoadStructure::in(TestSuite::getInstance()->rootPath);

        $this->parallel($arguments);
        $this->colors($arguments);

        return $arguments;
    }

    private function parallel(array &$arguments): void
    {
        $this->unsetArgument($arguments, '--parallel');
        $this->setArgument($arguments, '--runner', Runner::class);
    }

    private function colors(array &$arguments): void
    {
        $isDecorated = (new ArgvInput($arguments))->getParameterOption('--colors', 'always') !== 'never';

        foreach (['--colors', '--colors=always', '--colors=auto', '--colors=never'] as $value) {
            $this->unsetArgument($arguments, $value);
        }

        if ($isDecorated) {
            $this->setArgument($arguments, '--colors');
        }
    }

    private function setArgument(array &$arguments, string $key, string $value = null): void
    {
        $arguments[] = $key;

        if ($value !== null) {
            $arguments[] = $value;
        }
    }

    private function unsetArgument(array &$arguments, string $argument): bool
    {
        if (($key = array_search($argument, $arguments)) !== false) {
            unset($arguments[$key]);

            return true;
        }

        return false;
    }
}
