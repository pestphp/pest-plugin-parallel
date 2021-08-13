<?php

declare(strict_types=1);

namespace Pest\Parallel;

use Illuminate\Testing\ParallelRunner;
use ParaTest\Runners\PHPUnit\Options;
use Pest\Actions\LoadStructure;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Parallel\Paratest\Runner;
use Pest\Support\Arr;
use Pest\TestSuite;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Plugin implements HandlesArguments
{
    public function handleArguments(array $arguments): array
    {
        if (!$this->userWantsParallel($arguments)) {
            $this->markTestSuiteAsParallelIfRequired();

            return $arguments;
        }

        LoadStructure::in(TestSuite::getInstance()->rootPath);

        $this->parallel($arguments);
        $this->laravel($arguments);
        $this->colors($arguments);

        return $arguments;
    }

    private function userWantsParallel(array $arguments): bool
    {
        return in_array('--parallel', $arguments, true)
            || in_array('-p', $arguments, true);
    }

    private function markTestSuiteAsParallelIfRequired(): void
    {
        if (intval(Arr::get($_SERVER, 'PARATEST')) === 1) {
            $_SERVER['PEST_PARALLEL'] = 1;
        }
    }

    /**
     * @param array<int, string> $arguments
     */
    private function parallel(array &$arguments): void
    {
        $this->unsetArgument($arguments, '--parallel');
        $this->unsetArgument($arguments, '-p');
        $this->unsetArgument($arguments, '--processes');

        $this->setArgument($arguments, '--runner', Runner::class);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function laravel(array &$arguments): void
    {
        if (!class_exists(ParallelRunner::class)) {
            return;
        }

        if (!method_exists(ParallelRunner::class, 'resolveRunnerUsing')) {
            exit("Using parallel with Pest requires Laravel v8.53.0 or higher.");
        }

        ParallelRunner::resolveRunnerUsing(function(Options $options, OutputInterface $output) {
            return new Runner($options, $output);
        });
        $this->setArgument($arguments, '--runner', '\Illuminate\Testing\ParallelRunner');
    }

    /**
     * @param array<int, string> $arguments
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
