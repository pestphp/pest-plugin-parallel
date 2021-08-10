<?php

declare(strict_types=1);

namespace Pest\Parallel\Paratest;

use Exception;
use NunoMaduro\Collision\Adapters\Phpunit\TestResult;
use ParaTest\Logging\JUnit\Reader;
use ParaTest\Logging\JUnit\Writer;
use ParaTest\Logging\LogInterpreter;
use ParaTest\Runners\PHPUnit\EmptyLogFileException;
use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\ResultPrinter;
use ParaTest\Runners\PHPUnit\RunnerInterface;
use ParaTest\Runners\PHPUnit\SuiteLoader;
use Pest\Factories\TestCaseFactory;
use Pest\Parallel\Concerns\Paratest\HandlesCoverage;
use Pest\TestSuite;
use PHPUnit\TextUI\TestRunner;
use SebastianBergmann\Timer\Timer;
use Symfony\Component\Console\Output\OutputInterface;

final class Runner implements RunnerInterface
{
    use HandlesCoverage;

    private const CYCLE_SLEEP = 10000;

    /**
     * @var int
     */
    private $exitcode = -1;

    /** @var Options */
    private $options;

    /** @var ResultPrinter */
    private $printer;

    /**
     * A collection of ExecutableTest objects that have processes
     * currently running.
     *
     * @var PestRunnerWorker[]
     */
    private $running = [];

    /**
     * A collection of pending ExecutableTest objects that have
     * yet to run.
     *
     * @var ExecutableTest[]
     */
    private $pending = [];

    /** @var OutputInterface */
    private $output;

    /** @var LogInterpreter */
    private $interpreter;

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
        $this->interpreter = new LogInterpreter();
        $this->printer     = new ResultPrinter($this->interpreter, $output, $options);
        $this->timer       = new Timer();

        $this->initCoverage($this->options);
    }

    final public function run(): void
    {
        $this->timer->start();

        $this->load(new SuiteLoader($this->options, $this->output));

        $this->doRun();

        $this->complete();
    }

    /**
     * Builds the collection of pending ExecutableTest objects
     * to run. If functional mode is enabled $this->pending will
     * contain a collection of TestMethod objects instead of Suite
     * objects.
     */
    private function load(SuiteLoader $loader): void
    {
        $loader->load();

        $this->pending = array_merge(
            $loader->getSuites(),
            $this->loadPestTests(),
        );

        $this->sortPending();

        foreach ($this->pending as $pending) {
            $this->printer->addTest($pending);
        }
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

        $this->log();
        $this->logCoverage($this->options, $this->output);
        $readers = $this->interpreter->getReaders();
        foreach ($readers as $reader) {
            $reader->removeLog();
        }
    }

    /**
     * This is basically a slightly edited version of the recap provided by
     * Collision. We just alter it so that it understands Paratest.
     */
    private function writeRecap(): void
    {
        $types = [
            TestResult::FAIL    => $this->interpreter->getTotalFailures() + $this->interpreter->getTotalErrors(),
            TestResult::WARN    => $this->interpreter->getTotalWarnings(),
            TestResult::SKIPPED => $this->interpreter->getTotalSkipped(),
            TestResult::PASS    => $this->interpreter->getTotalTests() - $this->interpreter->getTotalFailures() - $this->interpreter->getTotalErrors() - $this->interpreter->getTotalWarnings() - $this->interpreter->getTotalSkipped(),
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

    /**
     * Write output to JUnit format if requested.
     */
    private function log(): void
    {
        if (($logJunit = $this->options->logJunit()) === null) {
            return;
        }

        $name = $this->options->path() ?? '';

        $writer = new Writer($this->interpreter, $name);
        $writer->write($logJunit);
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
            $this->interpreter->addReader(new Reader($executableTest->getTempFile()));
        } catch (EmptyLogFileException $emptyLogFileException) {
            throw $worker->getWorkerCrashedException($emptyLogFileException);
        }

        $this->addCoverage($executableTest, $this->options);

        return false;
    }

    /**
     * @return array<ExecutablePestTest>
     */
    private function loadPestTests(): array
    {
        $pestTestSuite = TestSuite::getInstance();

        $files = array_values(array_map(function (TestCaseFactory $factory): string {
            return $factory->filename;
        }, $pestTestSuite->tests->state));

        $occurrences = array_count_values($files);

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
}
