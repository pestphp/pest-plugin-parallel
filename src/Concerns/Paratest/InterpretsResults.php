<?php

declare(strict_types=1);

namespace Pest\Parallel\Concerns\Paratest;

use NunoMaduro\Collision\Adapters\Phpunit\TestResult;
use ParaTest\Logging\JUnit\Reader;
use ParaTest\Logging\JUnit\Writer;
use ParaTest\Logging\LogInterpreter;
use ParaTest\Runners\PHPUnit\EmptyLogFileException;
use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use SebastianBergmann\Timer\Duration;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
trait InterpretsResults
{
    /**
     * @var LogInterpreter|null
     */
    private $interpreter = null;

    private function getInterpreter(): LogInterpreter
    {
        if ($this->interpreter === null) {
            $this->interpreter = new LogInterpreter();
        }

        return $this->interpreter;
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

        $writer = new Writer($this->getInterpreter(), $name);
        $writer->write($logJunit);
    }

    /**
     * @throws EmptyLogFileException
     */
    private function addReaderForTest(ExecutableTest $test): void
    {
        $this->getInterpreter()->addReader(new Reader($test->getTempFile()));
    }

    /**
     * Remove any previously stored test result logs.
     */
    private function clearTestLogs(): void
    {
        foreach ($this->getInterpreter()->getReaders() as $reader) {
            $reader->removeLog();
        }
    }

    /**
     * The total number of recorded successful tests.
     */
    private function testsPassed(): int
    {
        return $this->getInterpreter()->getTotalTests()
            - $this->testsFailed()
            - $this->testsWithWarnings()
            - $this->testsSkipped();
    }

    /**
     * The total number of recorded test failures and errors combined.
     */
    private function testsFailed(): int
    {
        return $this->getInterpreter()->getTotalFailures() + $this->getInterpreter()->getTotalErrors();
    }

    /**
     * The total number of recorded test warnings.
     */
    private function testsWithWarnings(): int
    {
        return $this->getInterpreter()->getTotalWarnings();
    }

    /**
     * The total number of recorded test skips.
     */
    private function testsSkipped(): int
    {
        return $this->getInterpreter()->getTotalSkipped();
    }

    /**
     * This is basically a slightly tweaked version of the recap provided by
     * Collision. We just alter it so that it understands Paratest.
     */
    private function printRecap(OutputInterface $output, Duration $duration): void
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

        $output->write([
            "\n",
            sprintf(
                '  <fg=white;options=bold>Tests:  </><fg=default>%s</>',
                implode(', ', $tests)
            ),
        ]);

        $timeElapsed = number_format($duration->asSeconds(), 2, '.', '');

        $output->writeln([
                '',
                sprintf(
                    '  <fg=white;options=bold>Time:   </><fg=default>%ss</>',
                    $timeElapsed
                ),
                '',
            ]
        );
    }
}
