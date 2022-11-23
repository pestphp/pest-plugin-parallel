<?php

declare(strict_types=1);

namespace Pest\Parallel\Paratest;

use ParaTest\Logging\JUnit\Reader;
use ParaTest\Runners\PHPUnit\BaseRunner;
use ParaTest\Runners\PHPUnit\EmptyLogFileException;
use ParaTest\Runners\PHPUnit\Options;
use Pest\Actions\InteractsWithPlugins;
use Pest\Parallel\Concerns\Paratest\InterpretsResults;
use Pest\Parallel\Support\OutputHandler;
use Pest\TestSuite;
use PHPUnit\TextUI\TestRunner;
use SebastianBergmann\Timer\Timer;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 *
 * @phpstan-ignore-next-line
 */
final class Runner extends BaseRunner
{
    use InterpretsResults;

    /**
     * The Pest test suite.
     *
     * @var TestSuite
     */
    private $testSuite;

    /**
     * A timer used to track the duration of the test suite.
     *
     * @var Timer
     */
    private $timer;

    /**
     * @var array<PestRunnerWorker>
     */
    private $running = [];

    public function __construct(Options $options, OutputInterface $output)
    {
        parent::__construct($options, $output);

        $this->testSuite   = TestSuite::getInstance();
        $this->timer       = new Timer();
    }

    /**
     * @return array<int, ExecutablePestTest>
     */
    protected function getPestTests(): array
    {
        $occurrences = array_count_values($this->testSuite->tests->getFilenames());

        return array_values(array_map(function (int $occurrences, string $file): ExecutablePestTest {
            return new ExecutablePestTest(
                $file,
                $occurrences,
                $this->options->hasCoverage(),
                $this->options->hasLogTeamcity(),
                $this->options->tmpDir(),
            );
        }, $occurrences, array_keys($occurrences)));
    }

    protected function doRun(): void
    {
        $this->timer->start();

        $this->output->writeln(['', sprintf(
            '  <options=bold>Running Pest in parallel using %s process%s</>',
            $this->options->processes(),
            $this->options->processes() > 1 ? 'es' : '',
        )]);

        $this->startWorkers();
        $this->assignAllPendingTests();
        $this->waitForAllToFinish();
    }

    private function startWorkers(): void
    {
        for ($token = 1; $token <= $this->options->processes(); $token++) {
            $this->running[$token] = new PestRunnerWorker($this->output, $this->options, $token);
            $this->running[$token]->start();
        }
    }

    private function assignAllPendingTests(): void
    {
        $phpunit        = $this->options->phpunit();
        $phpunitOptions = $this->options->filtered();

        while (count($this->pending) > 0 && count($this->running) > 0) {
            foreach ($this->running as $worker) {
                if (!$worker->isRunning()) {
                    throw $worker->getWorkerCrashedException();
                }

                if (!$worker->isFree()) {
                    continue;
                }

                $this->tearDown($worker);
                if ($this->getExitCode() > 0 && $this->shouldStopOnFailure()) {
                    $this->pending = [];
                } elseif (($pending = array_shift($this->pending)) !== null) {
                    $worker->assign($pending, $phpunit, $phpunitOptions, $this->options);
                }
            }

            usleep(self::CYCLE_SLEEP);
        }
    }

    private function waitForAllToFinish(): void
    {
        $stopped = [];
        while (count($this->running) > 0) {
            foreach ($this->running as $index => $worker) {
                if ($worker->isRunning()) {
                    if (!array_key_exists($index, $stopped) && $worker->isFree()) {
                        $worker->stop();
                        $stopped[$index] = true;
                    }

                    continue;
                }

                if (!$worker->isFree()) {
                    throw $worker->getWorkerCrashedException();
                }

                $this->tearDown($worker);
                unset($this->running[$index]);
            }

            usleep(self::CYCLE_SLEEP);
        }
    }

    private function addCoverageIfHas(PestRunnerWorker $worker): void
    {
        if ($this->hasCoverage() && $worker->hasCurrentlyExecuting()) {
            $coverageMerger   = $this->getCoverage();
            $coverageFileName = $worker->getCoverageFileName();

            if ($coverageMerger === null) {
                return;
            }

            $coverageMerger->addCoverageFromFile($coverageFileName);
        }
    }

    private function tearDown(PestRunnerWorker $worker): void
    {
        if (!$worker->hasCurrentlyExecuting()) {
            return;
        }

        $this->addCoverageIfHas($worker);

        $exitCode = TestRunner::SUCCESS_EXIT;

        try {
            $this->addReaderForTest($worker->getExecutableTest());

            $reader = new Reader($worker->getExecutableTest()->getTempFile());

            if ($reader->getTotalErrors() > 0) {
                $exitCode = TestRunner::EXCEPTION_EXIT;
            } elseif ($reader->getTotalFailures() > 0 || $reader->getTotalWarnings() > 0) {
                $exitCode = TestRunner::FAILURE_EXIT;
            }
        } catch (EmptyLogFileException $emptyLogFileException) {
            throw $worker->getWorkerCrashedException($emptyLogFileException);
        }

        $worker->printOutput();

        $worker->reset();

        $this->exitcode = max($this->getExitCode(), $exitCode);

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
    }

    private function shouldStopOnFailure(): bool
    {
        if ($this->options->stopOnFailure()) {
            return true;
        }

        if ($this->options->configuration() === null) {
            return false;
        }

        return $this->options->configuration()->phpunit()->stopOnFailure();
    }

    protected function complete(): void
    {
        foreach (OutputHandler::$additionalOutput as $output) {
            $this->output->write($output);
        }

        OutputHandler::reset();

        $this->printRecap($this->output, $this->timer->stop());

        $this->logToJUnit($this->options);
        $this->logCoverage();

        $this->exitcode = InteractsWithPlugins::addOutput($this->getExitCode());

        $this->clearTestLogs();
    }

    protected function beforeLoadChecks(): void
    {
    }
}
