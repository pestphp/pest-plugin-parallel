<?php

namespace Pest\Parallel\Support;

use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;

class PendingTestDetail
{
    /**
     * The test that should be run.
     *
     * @var ExecutableTest
     */
    private $executableTest;

    /**
     * The options that should be passed to the test.
     *
     * @var Options
     */
    private $options;

    /**
     * The unique process token for the test.
     *
     * @var int
     */
    private $token;

    public function __construct(
        ExecutableTest $executableTest,
        Options $options,
        int $token
    )
    {
        $this->executableTest = $executableTest;
        $this->options = $options;
        $this->token = $token;
    }

    public function getExecutableTest(): ExecutableTest
    {
        return $this->executableTest;
    }

    public function getOptions(): Options
    {
        return $this->options;
    }

    public function getToken(): int
    {
        return $this->token;
    }
}
