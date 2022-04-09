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
     * @var array<PestRunnerWorker>
     */
    private $running = [];

    protected function doRun(): void
    {
        $this->timer->start();

        $this->output->writeln(['', sprintf(
            '  <options=bold>Running Pest in parallel using %s process%s</>',
            $this->options->processes(),
            $this->options->processes() > 1 ? 'es' : '',
        )]);

        $this->createWorkers();
    }

    private function createWorkers(): void
    {
        $availableTokens = range(1, $this->options->processes());
        while (count($this->running) > 0 || count($this->pending) > 0) {
            $this->fillRunQueue($availableTokens);
            usleep(static::CYCLE_SLEEP);

            $availableTokens = [];

            $completedTests = array_filter($this->running, function (PestRunnerWorker $test): bool {
                return !$test->isRunning();
            });

            foreach ($completedTests as $token => $test) {
                $this->tearDown($test);
                unset($this->running[$token]);
                $availableTokens[] = $token;
            }
        }
    }

    /**
     * @param array<int, int> $availableTokens
     */
    private function fillRunQueue(array $availableTokens): void
    {
        while (
            count($this->pending) > 0
            && count($this->running) < $this->options->processes()
            && ($token = array_shift($availableTokens)) !== null
        ) {
            $pendingTestDetail = new PendingTestDetail(array_shift($this->pending), $this->options, $token);

            $this->running[$token] = new PestRunnerWorker($this->output, $pendingTestDetail);
            $this->running[$token]->run();
        }
    }

    private function tearDown(PestRunnerWorker $worker): void
    {
        $this->exitcode = max($this->getExitCode(), (int) $worker->stop());

        if ($this->shouldStopOnFailure() && $this->getExitCode() > TestRunner::SUCCESS_EXIT) {
            $this->pending = [];
        }

        if (
            $this->exitcode > TestRunner::SUCCESS_EXIT
            && $this->exitcode !== TestRunner::FAILURE_EXIT
            && $this->exitcode !== TestRunner::EXCEPTION_EXIT
        ) {
            throw $worker->getWorkerCrashedException();
        }

        try {
            $this->addReaderForTest($worker->getExecutableTest());
        } catch (EmptyLogFileException $emptyLogFileException) {
            throw $worker->getWorkerCrashedException($emptyLogFileException);
        }

        $coverageMerger = $this->getCoverage();

        if ($coverageMerger === null) {
            return;
        }

        $coverageMerger->addCoverageFromFile($worker->getExecutableTest()->getCoverageFileName());
    }
}
