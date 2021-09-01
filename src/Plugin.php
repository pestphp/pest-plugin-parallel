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

        $arguments = $this->parallel($arguments);
        $arguments = $this->laravel($arguments);

        return $this->colors($arguments);
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
     *
     * @return array<int, string>
     */
    private function parallel(array $arguments): array
    {
        $arguments = $this->unsetArgument($arguments, '--parallel');
        $arguments = $this->unsetArgument($arguments, '-p');

        return $this->setArgument($arguments, '--runner', Runner::class);
    }

    /**
     * @param array<int, string> $arguments
     *
     * @return array<int, string>
     */
    private function laravel(array $arguments): array
    {
        // We check for the Kernel because that proves we're in a Laravel application
        // rather than just a Laravel package.
        if (!class_exists('\App\Http\Kernel') || !class_exists(ParallelRunner::class)) {
            return $arguments;
        }

        // @phpstan-ignore-next-line
        if (!method_exists(ParallelRunner::class, 'resolveRunnerUsing')) {
            exit('Using parallel with Pest requires Laravel v8.55.0 or higher.');
        }

        ParallelRunner::resolveRunnerUsing(function (Options $options, OutputInterface $output): Runner {
            return new Runner($options, $output);
        });

        $arguments = $this->unsetArgument($arguments, '--runner');

        return $this->setArgument($arguments, '--runner', '\Illuminate\Testing\ParallelRunner');
    }

    /**
     * @param array<int, string> $arguments
     *
     * @return array<int, string>
     */
    private function colors(array $arguments): array
    {
        $isDecorated = (new ArgvInput($arguments))->getParameterOption('--colors', 'always') !== 'never';

        $arguments = $this->unsetArgument($arguments, '--colors');

        if ($isDecorated) {
            $arguments = $this->setArgument($arguments, '--colors');
        }

        return $arguments;
    }

    /**
     * @param array<int, string> $arguments
     *
     * @return array<int, string>
     */
    private function setArgument(array &$arguments, string $key, string $value = ''): array
    {
        $argument = $key;

        if ($value !== '') {
            $argument .= "={$value}";
        }

        $arguments[] = $argument;

        return $arguments;
    }

    /**
     * @param array<string> $arguments
     *
     * @return array<int, string>
     */
    private function unsetArgument(array &$arguments, string $argument): array
    {
        return array_filter($arguments, function ($value) use ($argument): bool {
            return strpos($value, $argument) !== 0;
        });
    }
}
