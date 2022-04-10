<?php

declare(strict_types=1);

namespace Pest\Parallel\Paratest;

use ParaTest\Runners\PHPUnit\EmptyLogFileException;
use Pest\Parallel\Contracts\RunningTest;
use Pest\Parallel\Support\PendingTestDetail;
use PHPUnit\TextUI\TestRunner;

/**
 * @internal
 *
 * @phpstan-ignore-next-line
 */
final class Runner extends BaseRunner
{
    protected function beforeRun(): void
    {
        $this->output->writeln(['', sprintf(
            '  <options=bold>Running Pest in parallel using %s process%s</>',
            $this->options->processes(),
            $this->options->processes() > 1 ? 'es' : '',
        )]);
    }

    protected function createRunningTest(PendingTestDetail $pendingTestDetail): RunningTest
    {
        $runner = new PestRunnerWorker($this->output, $pendingTestDetail);
        $runner->run();

        return $runner;
    }

    /**
     * @param PestRunnerWorker $test
     */
    protected function tearDownTest(RunningTest $test): void
    {
        $this->exitcode = max($this->getExitCode(), (int) $test->stop());

        if ($this->shouldStopOnFailure() && $this->getExitCode() > TestRunner::SUCCESS_EXIT) {
            $this->pending = [];
        }

        if (
            $this->exitcode > TestRunner::SUCCESS_EXIT
            && $this->exitcode !== TestRunner::FAILURE_EXIT
            && $this->exitcode !== TestRunner::EXCEPTION_EXIT
        ) {
            throw $test->getWorkerCrashedException();
        }

        try {
            $this->addReaderForTest($test->getExecutableTest());
        } catch (EmptyLogFileException $emptyLogFileException) {
            throw $test->getWorkerCrashedException($emptyLogFileException);
        }

        $coverageMerger = $this->getCoverage();

        if ($coverageMerger === null) {
            return;
        }

        $coverageMerger->addCoverageFromFile($test->getExecutableTest()->getCoverageFileName());
    }
}
