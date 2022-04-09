<?php

namespace Pest\Parallel\Serverless\Sidecar\Functions;

use Hammerstone\Sidecar\LambdaFunction;
use Pest\Parallel\Support\PendingTestDetail;

final class RunTest extends LambdaFunction
{
    /**
     * @var PendingTestDetail
     */
    private $pendingTestDetail;

    public function __construct(PendingTestDetail $pendingTestDetail)
    {
        $this->pendingTestDetail = $pendingTestDetail;
    }

    public function handler()
    {
        // TODO: Implement handler() method.
    }

    public function package()
    {
        // TODO: Implement package() method.
    }
}
