<?php

declare(strict_types=1);

namespace Pest\Parallel;

use Pest\Actions\LoadStructure;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Parallel\Paratest\Runner;
use Pest\Support\Arr;
use Pest\TestSuite;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * @internal
 */
final class Plugin implements HandlesArguments
{
    public function handleArguments(array $arguments): array
    {
        if (!in_array('--parallel', $arguments, true)) {
            $this->markTestSuiteAsParallelIfRequired();

            return $arguments;
        }

        LoadStructure::in(TestSuite::getInstance()->rootPath);

        $this->parallel($arguments);
        $this->colors($arguments);

        return $arguments;
    }

    private function markTestSuiteAsParallelIfRequired(): void
    {
        if (Arr::get($_SERVER, 'PARATEST') !== null) {
            TestSuite::getInstance()->isInParallel = true;
        }
    }

    /**
     * @param array<string> $arguments
     */
    private function parallel(array &$arguments): void
    {
        $this->unsetArgument($arguments, '--parallel');
        $this->unsetArgument($arguments, '-P');

        $this->setArgument($arguments, '--runner', Runner::class);
    }

    /**
     * @param array<string> $arguments
     */
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

    /**
     * @param array<int, string> $arguments
     */
    private function setArgument(array &$arguments, string $key, string $value = ''): void
    {
        $arguments[] = $key;

        if (strlen($value) > 0) {
            $arguments[] = $value;
        }
    }

    /**
     * @param array<string> $arguments
     */
    private function unsetArgument(array &$arguments, string $argument): bool
    {
        if (($key = array_search($argument, $arguments, true)) !== false) {
            unset($arguments[$key]);

            return true;
        }

        return false;
    }
}
