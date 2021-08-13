<?php

declare(strict_types=1);

namespace Pest\Parallel\Concerns\Paratest;

use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\SuiteLoader;
use Pest\Factories\TestCaseFactory;
use Pest\Parallel\Paratest\ExecutablePestTest;
use Pest\TestSuite;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
trait LoadsTests
{
    /**
     * @return array<ExecutableTest>
     */
    private function loadPhpUnitTests(Options $options, OutputInterface $output): array
    {
        $loader = new SuiteLoader($options, $output);
        $loader->load();

        return $loader->getSuites();
    }

    /**
     * @return array<ExecutablePestTest>
     */
    private function loadPestTests(Options $options): array
    {
        $pestTestSuite = TestSuite::getInstance();

        $occurrences = array_count_values($pestTestSuite->tests->getFilenames());

        return array_values(array_map(function (int $occurrences, string $file) use ($options): ExecutablePestTest {
            return new ExecutablePestTest(
                $file,
                $occurrences,
                $options->hasCoverage(),
                $options->hasLogTeamcity(),
                $options->tmpDir(),
            );
        }, $occurrences, array_keys($occurrences)));
    }
}
