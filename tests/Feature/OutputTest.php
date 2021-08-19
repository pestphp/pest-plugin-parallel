<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

beforeEach(function () {
    $process = new Process(['php', 'vendor/bin/pest', '--parallel', '--group', 'runnable', '--processes=8'], dirname(__DIR__ . '/../../../'));
    $process->run();

    $this->output = $process->getOutput();
});

it('includes the test case names', function () {
    expect($this->output)->toContain(' Tests\\InternalRunnableTests\\ExampleTest');
});

it('includes a list of test outcomes', function () {
    expect(strip_tags($this->output))
        ->toContain('it passes once')
        ->toContain('it passes twice')
        ->toContain('it also fails')
        ->toContain('it also fails again');
});

it('includes an output of all errors', function () {
    expect($this->output)
        ->toContain('Failed asserting that true is false.')
        ->toContain('Failed asserting that false is true.');
});

it('includes a summary', function () {
    expect($this->output)
        ->toContain('Tests:  2 failed, 2 passed')
        ->toMatch('/Time:   .*s/');
});

it('includes information about the number of processes being run', function () {
    expect($this->output)->toContain('Running Pest in parallel using 8 processes');
});
