<?php

declare(strict_types=1);

namespace Pest\Parallel\Paratest;

use ParaTest\Runners\PHPUnit\ExecutableTest;

/**
 * @internal
 *
 * @phpstan-ignore-next-line
 */
final class ExecutablePestTest extends ExecutableTest
{
    /**
     * The number of tests in this file.
     *
     * @var int
     */
    private $testCount;

    public function __construct(string $path, int $testCount, bool $needsCoverage, bool $needsTeamcity, string $tmpDir)
    {
        parent::__construct($path, $needsCoverage, $needsTeamcity, $tmpDir);
        $this->testCount = $testCount;
    }

    public function getTestCount(): int
    {
        return $this->testCount;
    }

    protected function prepareOptions(array $options): array
    {
        return $options;
    }
}
