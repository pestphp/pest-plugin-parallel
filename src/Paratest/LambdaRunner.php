<?php

namespace Pest\Parallel\Paratest;

use Hammerstone\Sidecar\Clients\LambdaClient;
use Hammerstone\Sidecar\Deployment;
use Hammerstone\Sidecar\Providers\SidecarServiceProvider;
use Hammerstone\Sidecar\Sidecar;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Pest\Parallel\Contracts\RunningTest;
use Pest\Parallel\Serverless\Sidecar\Functions\RunTest;
use Pest\Parallel\Support\PendingTestDetail;
use Pest\Parallel\Support\PromiseBasedRunningTest;

/**
 * @implements BaseRunner<\Pest\Parallel\Support\PromiseBasedRunningTest>
 */
class LambdaRunner extends BaseRunner
{
    /**
     * @var Application
     */
    private $app;

    protected function beforeRun(): void
    {
        $this->bootSidecar();

        // TODO: This is only needed because our account requires security tokens. We should remove it soon.
        $this->rebindLambdaClient();

        $this->ensureFunctionIsUploaded();

        $this->output->writeln(['', sprintf(
            '  <options=bold>Running Pest in parallel using %s lambda function%s</>',
            $this->options->processes(),
            $this->options->processes() > 1 ? 's' : '',
        )]);
    }

    /**
     * Sidecar currently requires Laravel to run. Until
     * Sidecar becomes framework-agnostic, we'll
     * boot Laravel in this step.
     */
    private function bootSidecar(): void
    {
        $laravelBootstrap = "{$this->testSuite->rootPath}/bootstrap/app.php";

        /*
         * If Laravel isn't available, we should inform the user
         * that their use-case is not currently supported.
         */
        if (!file_exists($laravelBootstrap)) {
            $this->output->write('Pest serverless currently requires a Laravel application.');

            exit(1);
        }

        $this->app = require_once $laravelBootstrap;
        $this->app->make(Kernel::class)->bootstrap();
    }

    private function rebindLambdaClient(): void
    {
        $this->app->singleton(LambdaClient::class, function () {
            return new LambdaClient([
                'version' => 'latest',
                'region' => config('sidecar.aws_region'),
                'credentials' => [
                    'key' => config('sidecar.aws_key'),
                    'secret' => config('sidecar.aws_secret'),
                    'token' => $_ENV['AWS_TOKEN'] ?? ''
                ],
            ]);
        });
    }

    /**
     * Before we actually execute any tests, we need to make
     * sure Lambda knows about our RunTest function! Let's
     * upload it here.
     */
    private function ensureFunctionIsUploaded(): void
    {
        $deployment = Deployment::make(RunTest::class)->deploy();
        $deployment->activate();
    }

    protected function createRunningTest(PendingTestDetail $pendingTestDetail): RunningTest
    {
        $pendingResult = Sidecar::executeAsync(RunTest::class, [
            'testDetail' => $pendingTestDetail,
        ]);

        return new PromiseBasedRunningTest($pendingResult);
    }

    protected function tearDownTest(RunningTest $test): void
    {
        dd($test);
    }
}
