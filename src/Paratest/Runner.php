<?php

declare(strict_types=1);

namespace Pest\Parallel\Paratest;

use ParaTest\Runners\PHPUnit\EmptyLogFileException;
use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\RunnerInterface;
use Pest\Parallel\Concerns\Paratest\HandlesCoverage;
use Pest\Parallel\Concerns\Paratest\InterpretsResults;
use Pest\Parallel\Concerns\Paratest\LoadsTests;
use Pest\Parallel\Concerns\Paratest\ManagesParallelism;
use Pest\Parallel\Support\OutputHandler;
use SebastianBergmann\Timer\Timer;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Runner implements RunnerInterface
{
    use LoadsTests;
    use ManagesParallelism;
    use InterpretsResults;
    use HandlesCoverage;

    /**
     * The provided Paratest Options.
     *
     * @var Options
     */
    private $options;

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

        $this->initCoverage($this->options);
    }

    final public function run(): void
    {
        $this->timer->start();
        $this->doRun($this->load(), $this->options, $this->output);
        $this->complete();
    }

    /**
     * Builds the collection of pending ExecutableTest objects
     * to run. This will be a mix of Pest tests and PhpUnit tests.
     *
     * @return array<ExecutableTest>
     */
    private function load(): array
    {
        $pendingTests = array_merge(
            $this->loadPhpUnitTests($this->options, $this->output),
            $this->loadPestTests($this->options)
        );

        return $this->sortPending($pendingTests);
    }

    /**
     * @param array<ExecutableTest> $pendingTests
     *
     * @return array<ExecutableTest>
     */
    private function sortPending(array $pendingTests): array
    {
        if ($this->options->orderBy() === Options::ORDER_RANDOM) {
            mt_srand($this->options->randomOrderSeed());
            shuffle($pendingTests);
        }

        if ($this->options->orderBy() !== Options::ORDER_REVERSE) {
            return $pendingTests;
        }

        return array_reverse($this->pending);
    }

    /**
     * Finalizes the run process. This method
     * prints all results, rewinds the log interpreter,
     * logs any results to JUnit, and cleans up temporary
     * files.
     */
    private function complete(): void
    {
        foreach (OutputHandler::$additionalOutput as $output) {
            $this->output->write($output);
        }

        OutputHandler::reset();

        $this->printRecap($this->output, $this->timer->stop());

        $this->logToJUnit($this->options);
        $this->logCoverage($this->options, $this->output);

        $this->clearTestLogs();
    }

    public function handleExecutedTest(ExecutableTest $test, PestRunnerWorker $worker): void
    {
        try {
            $this->addReaderForTest($test);
        } catch (EmptyLogFileException $emptyLogFileException) {
            throw $worker->getWorkerCrashedException($emptyLogFileException);
        }

        $this->addCoverage($test, $this->options);
    }
}
