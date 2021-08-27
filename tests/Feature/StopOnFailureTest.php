<?php

it('can be told to stop on failure in the command line', function () {
    $this->output = runInternalTests(['--stop-on-failure', '--processes=1'])->getOutput();

    expect($this->output)->toContain('1 failed');
});

it('can be told to stop on failure in the configuration file', function () {
    $this->output = runInternalTests(['--configuration=tests/configurations/phpunit.stop-on-failure.xml', '--processes=1'])->getOutput();

    expect($this->output)->toContain('1 failed');
});
