<?php

declare(strict_types=1);

namespace Pest\Parallel\Paratest;

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

        $this->testSuite = TestSuite::getInstance();
        $this->timer     = new Timer();
    }

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
            $executableTest = array_shift($this->pending);

            $this->running[$token] = new PestRunnerWorker($this->output, $executableTest, $this->options, $token);
            $this->running[$token]->run();
        }
    }

    private function tearDown(PestRunnerWorker $worker): void
    {
        $this->exitcode = max($this->getExitCode(), (int) $worker->stop());

        if ($this->options->stopOnFailure() && $this->getExitCode() > TestRunner::SUCCESS_EXIT) {
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
