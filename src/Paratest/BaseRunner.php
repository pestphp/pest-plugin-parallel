<?php

namespace Pest\Parallel\Paratest;

use ParaTest\Runners\PHPUnit\BaseRunner as ParatestRunner;
use ParaTest\Runners\PHPUnit\Options;
use Pest\Actions\InteractsWithPlugins;
use Pest\Parallel\Concerns\Paratest\InterpretsResults;
use Pest\Parallel\Contracts\RunningTest;
use Pest\Parallel\Support\OutputHandler;
use Pest\Parallel\Support\PendingTestDetail;
use Pest\TestSuite;
use SebastianBergmann\Timer\Timer;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @template TValue of \Pest\Parallel\Contracts\RunningTest
 */
abstract class BaseRunner extends ParatestRunner
{
    use InterpretsResults;

    /**
     * The Pest test suite.
     *
     * @var TestSuite
     */
    protected $testSuite;

    /**
     * A timer used to track the duration of the test suite.
     *
     * @var Timer
     */
    protected $timer;

    /**
     * Tests that are currently running.
     *
     * @var array<TValue>
     */
    protected $running = [];

    public function __construct(Options $options, OutputInterface $output)
    {
        parent::__construct($options, $output);

        $this->testSuite = TestSuite::getInstance();
        $this->timer     = new Timer();
    }

    /**
     * @return TValue
     */
    protected abstract function createRunningTest(PendingTestDetail $pendingTestDetail): RunningTest;

    /**
     * @param TValue $test
     */
    protected abstract function tearDownTest(RunningTest $test): void;

    /**
     * If there is any setup that should be performed
     * before building and executing tests, you
     * should override this method to do it.
     */
    protected function beforeRun(): void
    {

    }

    protected function doRun(): void
    {
        $this->timer->start();

        $this->beforeRun();

        $this->createTests();
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

    protected function shouldStopOnFailure(): bool
    {
        if ($this->options->stopOnFailure()) {
            return true;
        }

        if ($this->options->configuration() === null) {
            return false;
        }

        return $this->options->configuration()->phpunit()->stopOnFailure();
    }

    protected function createTests(): void
    {
        $availableTokens = range(1, $this->options->processes());

        while (count($this->running) > 0 || count($this->pending) > 0) {
            $this->fillRunQueue($availableTokens);

            usleep(static::CYCLE_SLEEP);

            $availableTokens = [];

            $completedTests = array_filter($this->running, function (RunningTest $test): bool {
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
