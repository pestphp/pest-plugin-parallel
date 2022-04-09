<?php

namespace Pest\Parallel\Paratest;

use GuzzleHttp\Promise\PromiseInterface;
use Hammerstone\Sidecar\Results\PendingResult;
use Hammerstone\Sidecar\Sidecar;
use Pest\Parallel\Serverless\Sidecar\Functions\RunTest;
use Pest\Parallel\Support\PendingTestDetail;

class LambdaRunner extends BaseRunner
{
    /**
     * @var array<PendingResult>
     */
    private $running = [];

    protected function doRun(): void
    {
        $this->timer->start();

        $this->bootSidecar();

        $this->output->writeln(['', sprintf(
            '  <options=bold>Running Pest in parallel using %s lambda function%s</>',
            $this->options->processes(),
            $this->options->processes() > 1 ? 's' : '',
        )]);

        $this->createLambdaFunctions();
    }

    /**
     * Sidecar currently requires Laravel to run. Until
     * Sidecar becomes framework-agnostic, we'll
     * boot Laravel in this step.
     */
    private function bootSidecar(): void
    {
        $laravelBootstrap = "{$this->testSuite->rootPath}/bootstrap/app.php";

        /**
         * If Laravel isn't available, we should inform the user
         * that their use-case is not currently supported.
         */
        if (! file_exists($laravelBootstrap)) {
            $this->output->write('Pest serverless currently requires a Laravel application.');

            exit(1);
        }

        $app = require_once $laravelBootstrap;
        $app->make(\Illuminate\Contracts\Http\Kernel::class);
    }

    private function createLambdaFunctions(): void
    {
        $availableTokens = range(1, $this->options->processes());

        while (count($this->running) > 0 || count($this->pending) > 0) {
            $this->fillRunQueue($availableTokens);

            usleep(static::CYCLE_SLEEP);

            $availableTokens = [];

            // A test is completed if the Guzzle promise is no longer in the "pending" state.
            $completedTests = array_filter($this->running, function (PendingResult $test): bool {
                return $test->rawPromise()->getState() !== PromiseInterface::PENDING;
            });

            foreach ($completedTests as $token => $test) {
                // TODO: We should tear down the test in order to handle errors, output and coverage
                //$this->tearDown($test);

                unset($this->running[$token]);
                $availableTokens[] = $token;
            }
        }
    }

    /**
     * @param array<int, int> $availableTokens
     */
    private function fillRunQueue(array $availableTokens): void
    {
        while (
            count($this->pending) > 0
            && count($this->running) < $this->options->processes()
            && ($token = array_shift($availableTokens)) !== null
        ) {
            $executableTest = array_shift($this->pending);

            $this->running[$token] = Sidecar::executeAsync(new RunTest(new PendingTestDetail($executableTest, $this->options, $token)));
        }
    }

    private function runningToPayload(): array
    {

    }
}
