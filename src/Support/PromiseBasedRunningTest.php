<?php

declare(strict_types=1);

namespace Pest\Parallel\Support;

use GuzzleHttp\Promise\PromiseInterface;
use Hammerstone\Sidecar\Results\PendingResult;
use Pest\Parallel\Contracts\RunningTest;

class PromiseBasedRunningTest implements RunningTest
{
    /**
     * The promise that is running the test.
     *
     * @var PendingResult
     */
    private $pendingResult;

    public function __construct(
        PendingResult $pendingResult
    )
    {
        $this->pendingResult = $pendingResult;
    }

    public function isFinished(): bool
    {
        return $this->pendingResult->rawPromise()->getState() !== PromiseInterface::PENDING;
    }
}
