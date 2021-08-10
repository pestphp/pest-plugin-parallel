<?php

declare(strict_types=1);

namespace Pest\Parallel\Paratest;

use Exception;
use NunoMaduro\Collision\Adapters\Phpunit\TestResult;
use ParaTest\Runners\PHPUnit\EmptyLogFileException;
use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\RunnerInterface;
use Pest\Parallel\Concerns\Paratest\HandlesCoverage;
use Pest\Parallel\Concerns\Paratest\InterpretsResults;
use Pest\Parallel\Concerns\Paratest\LoadsTests;
use PHPUnit\TextUI\TestRunner;
use SebastianBergmann\Timer\Timer;
use Symfony\Component\Console\Output\OutputInterface;

final class Runner implements RunnerInterface
{
    use LoadsTests;
    use InterpretsResults;
    use HandlesCoverage;

    private const CYCLE_SLEEP = 10000;

    /**
     * @var int
     */
    private $exitcode = -1;

    /**
     * The provided Paratest Options.
     *
     * @var Options
     */
    private $options;

    /**
     * A collection of ExecutableTest objects that have processes
     * currently running.
     *
     * @var PestRunnerWorker[]
     */
    private $running = [];

    /**
     * A collection of pending ExecutableTest objects that have yet to run.
     *
     * @var ExecutableTest[]
     */
    private $pending = [];

    /**
     * The output used for displaying information to the user.
     *
     * @var OutputInterface
     */
    private $output;

    /**
     * A timer used to track the duration of the test suite.
     *
     * @var Timer
     */
    private $timer;

    public function __construct(Options $options, OutputInterface $output)
    {
        $this->options     = $options;
        $this->output      = $output;
        $this->timer       = new Timer();

        $this->initInterpretsResults();
        $this->initCoverage($this->options);
    }

    final public function run(): void
    {
        $this->timer->start();
        $this->load();
        $this->doRun();
        $this->complete();
    }

    /**
     * Builds the collection of pending ExecutableTest objects
     * to run. This will be a mix of Pest tests and PhpUnit tests.
     */
    private function load(): void
    {
        $this->pending = array_merge($this->loadPhpUnitTests(), $this->loadPestTests(), );

        $this->sortPending();
    }

    private function sortPending(): void
    {
        if ($this->options->orderBy() === Options::ORDER_RANDOM) {
            mt_srand($this->options->randomOrderSeed());
            shuffle($this->pending);
        }

        if ($this->options->orderBy() !== Options::ORDER_REVERSE) {
            return;
        }

        $this->pending = array_reverse($this->pending);
    }

    /**
     * Finalizes the run process. This method
     * prints all results, rewinds the log interpreter,
     * logs any results to JUnit, and cleans up temporary
     * files.
     */
    private function complete(): void
    {
        foreach (PestRunnerWorker::$additionalOutput as $output) {
            $this->output->write($output);
        }
        PestRunnerWorker::$additionalOutput = [];

        $this->writeRecap();

        $this->logToJUnit($this->options);
        $this->logCoverage($this->options, $this->output);

        $this->clearTestLogs();
    }

    /**
     * This is basically a slightly edited version of the recap provided by
     * Collision. We just alter it so that it understands Paratest.
     */
    private function writeRecap(): void
    {
        $types = [
            TestResult::FAIL    => $this->testsFailed(),
            TestResult::WARN    => $this->testsWithWarnings(),
            TestResult::SKIPPED => $this->testsSkipped(),
            TestResult::PASS    => $this->testsPassed(),
        ];

        $tests = [];

        foreach ($types as $type => $number) {
            if ($number === 0) {
                continue;
            }

            $color   = TestResult::makeColor($type);
            $tests[] = "<fg=$color;options=bold>$number $type</>";
        }

        $this->output->write([
            "\n",
            sprintf(
                '  <fg=white;options=bold>Tests:  </><fg=default>%s</>',
                implode(', ', $tests)
            ),
        ]);

        $timeElapsed = number_format($this->timer->stop()->asSeconds(), 2, '.', '');
        $this->output->writeln([
                '',
                sprintf(
                    '  <fg=white;options=bold>Time:   </><fg=default>%ss</>',
                    $timeElapsed
                ),
            ]
        );

        $this->output->writeln('');
    }

    /**
     * Returns the highest exit code encountered
     * throughout the course of test execution.
     */
    final public function getExitCode(): int
    {
        return $this->exitcode;
    }

    private function doRun(): void
    {
        $availableTokens = range(1, $this->options->processes());
        while (count($this->running) > 0 || count($this->pending) > 0) {
            $this->fillRunQueue($availableTokens);
            usleep(self::CYCLE_SLEEP);

            $availableTokens = [];
            foreach ($this->running as $token => $test) {
                if ($this->testIsStillRunning($test)) {
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
    private function fillRunQueue(array $availableTokens): void
    {
        while (
            count($this->pending) > 0
            && count($this->running) < $this->options->processes()
            && ($token = array_shift($availableTokens)) !== null
        ) {
            $executableTest = array_shift($this->pending);

            $this->running[$token] = new PestRunnerWorker($this->output, $executableTest, $this->options, $token);
            $this->running[$token]->run();

            if ($this->options->verbosity() < Options::VERBOSITY_VERY_VERBOSE) {
                continue;
            }

            $cmd = $this->running[$token];
            $this->output->write("\nExecuting test via: {$cmd->getExecutableTest()->getLastCommand()}\n");
        }
    }

    /**
     * Returns whether or not a test has finished being
     * executed. If it has, this method also halts a test process - optionally
     * throwing an exception if a fatal error has occurred -
     * prints feedback, and updates the overall exit code.
     *
     * @throws Exception
     */
    private function testIsStillRunning(PestRunnerWorker $worker): bool
    {
        if ($worker->isRunning()) {
            return true;
        }

        $this->exitcode = max($this->exitcode, (int) $worker->stop());

        if ($this->options->stopOnFailure() && $this->exitcode > 0) {
            $this->pending = [];
        }

        if (
            $this->exitcode > 0
            && $this->exitcode !== TestRunner::FAILURE_EXIT
            && $this->exitcode !== TestRunner::EXCEPTION_EXIT
        ) {
            throw $worker->getWorkerCrashedException();
        }

        $executableTest = $worker->getExecutableTest();

        try {
            $this->addReaderForTest($executableTest);
        } catch (EmptyLogFileException $emptyLogFileException) {
            throw $worker->getWorkerCrashedException($emptyLogFileException);
        }

        $this->addCoverage($executableTest, $this->options);

        return false;
    }
}
