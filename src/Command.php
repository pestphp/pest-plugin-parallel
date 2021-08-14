<?php

declare(strict_types=1);

namespace Pest\Parallel;

use ParaTest\Console\Commands\ParaTestCommand;
use Pest\Actions\InteractsWithPlugins;
use Pest\TestSuite;
use Symfony\Component\Console\Input\ArgvInput;

final class Command
{
    /**
     * @param array<int, string> $argv
     */
    public function run(array $argv): int
    {
        $argv = InteractsWithPlugins::handleArguments($argv);

        $testSuite = TestSuite::getInstance();

        return ParaTestCommand::applicationFactory($testSuite->rootPath)->run(new ArgvInput($argv));
    }
}
