<?php

declare(strict_types=1);

namespace Pest\Parallel;

use Pest\Actions\LoadStructure;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Parallel\Arguments\Colors;
use Pest\Parallel\Arguments\Laravel;
use Pest\Parallel\Arguments\Parallel;
use Pest\Support\Arr;
use Pest\TestSuite;

/**
 * @internal
 */
final class Plugin implements HandlesArguments
{
    /**
     * The argument handlers to run before executing the test command.
     *
     * @var array<int, class-string>
     */
    private $handlers = [
        Parallel::class,
        Laravel::class,
        Colors::class,
    ];

    public function handleArguments(array $arguments): array
    {
        if (!$this->userWantsParallel($arguments)) {
            $this->markTestSuiteAsParallelIfRequired();

            return $arguments;
        }

        LoadStructure::in(TestSuite::getInstance()->rootPath);

        return array_reduce($this->handlers, function ($arguments, $class) {
            return (new $class())->handle($arguments);
        }, $arguments);
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
}
