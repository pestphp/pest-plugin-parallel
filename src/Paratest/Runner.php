<?php

declare(strict_types=1);

namespace Pest\Parallel\Paratest;

use ParaTest\Runners\PHPUnit\EmptyLogFileException;
use Pest\Parallel\Support\PendingTestDetail;
use PHPUnit\TextUI\TestRunner;

/**
 * @internal
 *
 * @phpstan-ignore-next-line
 */
final class Runner extends BaseRunner
{
    /**
     * Tests that are currently running.
     *
     * @var array<PestRunnerWorker>
     */
    protected $running = [];

    protected function beforeRun(): void
    {
        $this->output->writeln(['', sprintf(
            '  <options=bold>Running Pest in parallel using %s process%s</>',
            $this->options->processes(),
            $this->options->processes() > 1 ? 'es' : '',
        )]);
    }

    protected function createTests(): void
    {
        $availableTokens = range(1, $this->options->processes());

        while (count($this->running) > 0 || count($this->pending) > 0) {
            $this->fillRunQueue($availableTokens);

            usleep(static::CYCLE_SLEEP);

            $availableTokens = [];

            $completedTests = array_filter($this->running, function (PestRunnerWorker $test): bool {
                return $test->isFinished();
            });

            foreach ($completedTests as $token => $test) {
                $this->tearDownTest($test);

                unset($this->running[$token]);
                $availableTokens[] = $token;
            }
        }
    }

    /**
     * @param array<int, int> $availableTokens
     */
    protected function fillRunQueue(array $availableTokens): void
    {
        while (
            count($this->pending) > 0
            && count($this->running) < $this->options->processes()
            && ($token = array_shift($availableTokens)) !== null
        ) {
            $executableTest = array_shift($this->pending);

            $this->running[$token] = $this->createRunningTest(new PendingTestDetail($executableTest, $this->options, $token));
        }
    }

    protected function createRunningTest(PendingTestDetail $pendingTestDetail): PestRunnerWorker
    {
        $runner = new PestRunnerWorker($this->output, $pendingTestDetail);
        $runner->run();

        return $runner;
    }

    protected function tearDownTest(PestRunnerWorker $test): void
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
