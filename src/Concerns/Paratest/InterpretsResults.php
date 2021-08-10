<?php

declare(strict_types=1);

namespace Pest\Parallel\Concerns\Paratest;

use ParaTest\Logging\JUnit\Reader;
use ParaTest\Logging\JUnit\Writer;
use ParaTest\Logging\LogInterpreter;
use ParaTest\Runners\PHPUnit\EmptyLogFileException;
use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;

/**
 * @internal
 */
trait InterpretsResults
{
    /**
     * An interpreter used to calculate the outcome of the test results.
     *
     * @var LogInterpreter
     */
    private $interpreter;

    private function initInterpretsResults(): void
    {
        $this->interpreter = new LogInterpreter();
    }

    /**
     * Write the test output to a JUnit file.
     */
    private function logToJUnit(Options $options): void
    {
        if (($logJunit = $options->logJunit()) === null) {
            return;
        }

        $name = $options->path() ?? '';

        $writer = new Writer($this->interpreter, $name);
        $writer->write($logJunit);
    }

    /**
     * @throws EmptyLogFileException
     */
    private function addReaderForTest(ExecutableTest $test): void
    {
        $this->interpreter->addReader(new Reader($test->getTempFile()));
    }

    /**
     * Remove any previously stored test result logs.
     */
    private function clearTestLogs(): void
    {
        foreach ($this->interpreter->getReaders() as $reader) {
            $reader->removeLog();
        }
    }

    /**
     * The total number of recorded successful tests.
     */
    private function testsPassed(): int
    {
        return $this->interpreter->getTotalTests()
            - $this->testsFailed()
            - $this->testsWithWarnings()
            - $this->testsSkipped();
    }

    /**
     * The total number of recorded test failures and errors combined.
     */
    private function testsFailed(): int
    {
        return $this->interpreter->getTotalFailures() + $this->interpreter->getTotalErrors();
    }

    /**
     * The total number of recorded test warnings.
     */
    private function testsWithWarnings(): int
    {
        return $this->interpreter->getTotalWarnings();
    }

    /**
     * The total number of recorded test skips.
     */
    private function testsSkipped(): int
    {
        return $this->interpreter->getTotalSkipped();
    }
}
