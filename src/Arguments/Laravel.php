<?php

declare(strict_types=1);

namespace Pest\Parallel\Arguments;

use Illuminate\Testing\ParallelRunner;
use ParaTest\Runners\PHPUnit\Options;
use Pest\Parallel\Paratest\Runner;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Laravel extends Handler
{
    protected function editArgs(): void
    {
        if (!$this->isALaravelApplication()) {
            return;
        }

        $this->setLaravelParallelRunner();

        $this->unsetArgument('--runner')
            ->setArgument('--runner', '\Illuminate\Testing\ParallelRunner');
    }

    private function isALaravelApplication(): bool
    {
        return class_exists('\App\Http\Kernel') && class_exists(ParallelRunner::class);
    }

    private function setLaravelParallelRunner(): void
    {
        // @phpstan-ignore-next-line
        if (!method_exists(ParallelRunner::class, 'resolveRunnerUsing')) {
            exit('Using parallel with Pest requires Laravel v8.55.0 or higher.');
        }

        // @phpstan-ignore-next-line
        ParallelRunner::resolveRunnerUsing(function (Options $options, OutputInterface $output): Runner {
            return new Runner($options, $output);
        });
    }
}
