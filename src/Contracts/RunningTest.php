<?php

declare(strict_types=1);

namespace Pest\Parallel\Contracts;

interface RunningTest
{

    public function isFinished(): bool;

}
