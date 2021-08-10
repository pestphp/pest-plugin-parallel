<?php

declare(strict_types=1);

namespace Pest\Parallel\Concerns\Paratest;

use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\SuiteLoader;
use Pest\Factories\TestCaseFactory;
use Pest\Parallel\Paratest\ExecutablePestTest;
use Pest\TestSuite;

/**
 * @internal
 */
trait LoadsTests
{
    /**
     * @return array<ExecutableTest>
     */
    private function loadPhpUnitTests(): array
    {
        $loader = new SuiteLoader($this->options, $this->output);
        $loader->load();

        return $loader->getSuites();
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
