<?php

namespace Pest\Parallel\Paratest;

use Aws\Result;
use Closure;
use GuzzleHttp\Promise\Each;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Hammerstone\Sidecar\Clients\LambdaClient;
use Hammerstone\Sidecar\Deployment;
use Hammerstone\Sidecar\Package;
use Hammerstone\Sidecar\Results\SettledResult;
use Hammerstone\Sidecar\Sidecar;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use NunoMaduro\Collision\Adapters\Phpunit\Printer;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\Worker\NullPhpunitPrinter;
use Pest\Parallel\Contracts\RunningTest;
use Pest\Parallel\Serverless\Sidecar\Functions\RunTest;
use Pest\Parallel\Support\OutputHandler;
use Pest\Parallel\Support\PendingTestDetail;
use Pest\Parallel\Support\ProcessEnvironmentHandler;
use Symfony\Component\Console\Output\OutputInterface;
use function GuzzleHttp\Promise\queue;

class LambdaRunner extends BaseRunner
{
    /**
     * @var Application
     */
    private $app;

    /**
     * The current token being used when yielding a new RunTest.
     *
     * @var int
     */
    private $currentToken = 1;

    /**
     * @var array<string>
     */
    private $uploadedFiles = [];

    /**
     * @var OutputHandler
     */
    private $outputHandler;

    public function __construct(Options $options, OutputInterface $output)
    {
        parent::__construct($options, $output);

        $this->outputHandler = new OutputHandler($this->output);
    }

    protected function beforeRun(): void
    {
        $this->bootSidecar();

        Sidecar::addLogger(function ($message) {
            $this->output->writeln($message);
        });

        // TODO: This is only needed because our account requires security tokens. We should remove it soon.
        $this->rebindLambdaClient();
        $this->ensureFunctionIsUploaded();
        $this->uploadPackages();

        Sidecar::warm([RunTest::class]);

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
        /**
         * Note that we have also had to manually add the `token` for
         * `\Hammerstone\Sidecar\Package::registerStreamWrapper()`
         * in the vendor folder too.
         *
         * We should make a PR for Sidecar that injects the S3Client
         * instead.
         */
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
        Deployment::make(RunTest::class)->deploy()->activate();
    }

    private function uploadPackages(): void
    {
        $packages = [
            Package::make()
                ->setBasePath($this->testSuite->rootPath)
                ->include('*')
                ->exclude([
                    ".git/",
                    ".idea/",
                    "vendor/",
                    "node_modules/",
                    "tests/",
                    ".phpunit.result.cache",
                ]),
            Package::make()
                ->setBasePath($this->testSuite->rootPath)
                ->include(["vendor/"]),
            Package::make()
                ->setBasePath($this->testSuite->rootPath)
                ->include(["tests/"]),
        ];

        array_walk($packages, function (Package $package) {
            $bucket = config('sidecar.aws_bucket');

            $this->uploadedFiles[$package->getFilename()] = "s3://{$bucket}/{$package->getFilename()}";
        });

        array_walk($packages, function (Package $package) {
            $package->upload();
        });
    }

    protected function createTests(): void
    {
        Each::ofLimit(
            $this->yieldPromises(),
            $this->options->processes(),
            Closure::fromCallable([$this, 'tearDownTest']),
            Closure::fromCallable([$this, 'tearDownTest'])
        )->wait();
    }

    protected function yieldPromises(): iterable
    {
        foreach ($this->pending as $test) {
            yield $this->createRunningTest(new PendingTestDetail($test, $this->options, $this->nextToken()));
        }
    }

    private function nextToken(): int
    {
        $availableTokens = range(1, $this->options->processes());
        $token = $this->currentToken;

        if ($token === array_values(array_flip($availableTokens))[0]) {
            $this->currentToken = 0;
        }

        $this->currentToken++;

        return $token;
    }

    protected function createRunningTest(PendingTestDetail $pendingTestDetail): PromiseInterface
    {
        $passthruPhp = $this->options->passthruPhp() ? $this->options->passthruPhp() : [];

        $testCommand = $pendingTestDetail->getExecutableTest()->commandArguments(
            'vendor/bin/pest',
            $this->options->filtered(),
            $this->options->passthru(),
        );

        $args = array_merge($passthruPhp, $testCommand);
        $printerIndex = array_search('--printer', $args, true);
        unset($args[$printerIndex]);
        unset($args[$printerIndex + 1]);

        $pendingResult = Sidecar::executeAsync(RunTest::class, [
            'testCommand' => $args,
            'env' => array_merge(
                $this->options->fillEnvWithTokens($pendingTestDetail->getToken()),
                (new ProcessEnvironmentHandler($args))->getTokens()
            ),
            'localCwd' => $this->options->cwd(),
            'tempFile' => $pendingTestDetail->getExecutableTest()->getTempFile(),
            'filesToDownload' => $this->uploadedFiles,
        ]);

        return $pendingResult->rawPromise();
    }

    protected function tearDownTest(Result $result, int $index): void
    {
        $test = $this->pending[$index];

        $result = (new SettledResult($result, new RunTest()))->throw();

        $this->outputHandler->handle($result->body()['output']);

        $exitCode = $result->body()['code'];

        if ($exitCode > $this->exitcode) {
            $this->exitcode = $exitCode;
        }
    }
}
