<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

uses()->group('runnable')
    ->beforeEach(function () {
        if (! ($_SERVER['PEST_PARALLEL'] ?? false)) {
            $this->markTestSkipped('This test is run as part of internal test runner.');
        }
    })
    ->in('InternalRunnableTests');

function runInternalTests(array $arguments): Process
{
    $process = new Process(array_merge(['php', 'vendor/bin/pest', '--parallel', '--group', 'runnable', '--exclude-group', 'exclude'], $arguments), dirname(__DIR__.'/../../'));
    $process->run();

    return $process;
}
