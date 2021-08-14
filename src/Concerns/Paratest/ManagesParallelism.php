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
     * The number of microseconds to sleep for before checking
     * active runners for completed tests.
     *
     * @readonly
     *
     * @var int
     */
    private $cycleSleep = 10000;

    /**
     * The highest current exit code recorded by the test runners.
     *
     * @var int
     */
    private $exitCode = -1;

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

            $completedTests = array_filter($this->running, function (PestRunnerWorker $test): bool {
                return !$test->isRunning();
            });

            foreach ($completedTests as $token => $test) {
                $this->tearDown($test, $options);
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

    private function tearDown(PestRunnerWorker $worker, Options $options): void
    {
        $this->exitCode = max($this->exitCode, (int) $worker->stop());

        if ($options->stopOnFailure() && $this->exitCode > TestRunner::SUCCESS_EXIT) {
            $this->pending = [];
        }

        if (
            $this->exitCode > TestRunner::SUCCESS_EXIT
            && $this->exitCode !== TestRunner::FAILURE_EXIT
            && $this->exitCode !== TestRunner::EXCEPTION_EXIT
        ) {
            throw $worker->getWorkerCrashedException();
        }

        $this->handleExecutedTest($worker->getExecutableTest(), $worker);
    }

    abstract public function handleExecutedTest(ExecutableTest $test, PestRunnerWorker $worker): void;

    final public function getExitCode(): int
    {
        return $this->exitCode;
    }
}
