<?php

declare(strict_types=1);

namespace Pest\Parallel\Concerns\Paratest;

use Exception;
use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use Pest\Parallel\Paratest\PestRunnerWorker;
use PHPUnit\TextUI\TestRunner;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
trait ManagesParallelism
{
    /**
     * The highest current exit code recorded by the test runners.
     *
     * @var int
     */
    private $exitcode = -1;

    /**
     * The number of microseconds to sleep for before checking
     * active runners for completed tests.
     *
     * @readonly
     *
     * @var int
     */
    private $cycleSleep = 10000;

    /**
     * A collection of pending ExecutableTest objects that have yet to run.
     *
     * @var ExecutableTest[]
     */
    private $pending = [];

    /**
     * A collection of ExecutableTest objects that have processes
     * currently running.
     *
     * @var array<PestRunnerWorker>
     */
    private $running = [];

    /**
     * @param array<ExecutableTest> $pendingTests
     *
     * @throws Exception
     */
    private function doRun(array $pendingTests, Options $options, OutputInterface $output): void
    {
        $this->pending = $pendingTests;

        $availableTokens = range(1, $options->processes());
        while (count($this->running) > 0 || count($this->pending) > 0) {
            $this->fillRunQueue($availableTokens, $options, $output);
            usleep($this->cycleSleep);

            $availableTokens = [];
            foreach ($this->running as $token => $test) {
                if ($this->testIsStillRunning($test, $options)) {
                    continue;
                }

                unset($this->running[$token]);
                $availableTokens[] = $token;
            }
        }
    }

    /**
     * @param array<int, int> $availableTokens
     */
    private function fillRunQueue(array $availableTokens, Options $options, OutputInterface $output): void
    {
        while (
            count($this->pending) > 0
            && count($this->running) < $options->processes()
            && ($token = array_shift($availableTokens)) !== null
        ) {
            $executableTest = array_shift($this->pending);

            $this->running[$token] = new PestRunnerWorker($output, $executableTest, $options, $token);
            $this->running[$token]->run();
        }
    }

    /**
     * Returns whether a test has finished being
     * executed. If it has, this method also halts a test process - optionally
     * throwing an exception if a fatal error has occurred -
     * prints feedback, and updates the overall exit code.
     *
     * @throws Exception
     */
    private function testIsStillRunning(PestRunnerWorker $worker, Options $options): bool
    {
        if ($worker->isRunning()) {
            return true;
        }

        $this->exitcode = max($this->exitcode, (int) $worker->stop());

        if ($options->stopOnFailure() && $this->exitcode > 0) {
            $this->pending = [];
        }

        if (
            $this->exitcode > 0
            && $this->exitcode !== TestRunner::FAILURE_EXIT
            && $this->exitcode !== TestRunner::EXCEPTION_EXIT
        ) {
            throw $worker->getWorkerCrashedException();
        }

        $this->handleExecutedTest($worker->getExecutableTest(), $worker);

        return false;
    }

    abstract public function handleExecutedTest(ExecutableTest $test, PestRunnerWorker $worker): void;

    final public function getExitCode(): int
    {
        return $this->exitcode;
    }
}
