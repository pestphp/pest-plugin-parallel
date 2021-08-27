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

    /**
     * @param array<int, string> $arguments
     */
    private function userWantsParallel(array $arguments): bool
    {
        return in_array('--parallel', $arguments, true)
            || in_array('-p', $arguments, true);
    }

    private function markTestSuiteAsParallelIfRequired(): void
    {
        if ((int) Arr::get($_SERVER, 'PARATEST') === 1) {
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

        $this->setArgument($arguments, '--runner', Runner::class);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function laravel(array &$arguments): void
    {
        // We check for the Kernel because that proves we're in a Laravel application
        // rather than just a Laravel package.
        if (!class_exists('\App\Http\Kernel') || !class_exists(ParallelRunner::class)) {
            return;
        }

        // @phpstan-ignore-next-line
        if (!method_exists(ParallelRunner::class, 'resolveRunnerUsing')) {
            exit('Using parallel with Pest requires Laravel v8.55.0 or higher.');
        }

        ParallelRunner::resolveRunnerUsing(function (Options $options, OutputInterface $output): Runner {
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

        $this->unsetArgument($arguments, '--colors');

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

        if ($value !== '') {
            $arguments[] = $value;
        }
    }

    /**
     * @param array<string> $arguments
     */
    private function unsetArgument(array &$arguments, string $argument): bool
    {
        $locatedKeys = array_keys(array_filter($arguments, function ($value) use ($argument): bool {
            return strpos($value, $argument) === 0;
        }));

        if (count($locatedKeys) > 0) {
            unset($arguments[$locatedKeys[0]]);

            return true;
        }

        return false;
    }
}
