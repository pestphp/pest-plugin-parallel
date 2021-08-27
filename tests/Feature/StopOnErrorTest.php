<?php

it('can be told to stop on error in the configuration file', function () {
    $this->output = runInternalTests(['--configuration=tests/configurations/phpunit.stop-on-error.xml', '--processes=1'])->getOutput();

    // 3 failures and 1 error. The 4th failure should never fire.
    expect($this->output)->toContain('4 failed');
});
