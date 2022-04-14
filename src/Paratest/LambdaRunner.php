<?php

namespace Pest\Parallel\Paratest;

use Aws\Result;
use Closure;
use GuzzleHttp\Promise\Each;
use GuzzleHttp\Promise\PromiseInterface;
use Hammerstone\Sidecar\Clients\LambdaClient;
use Hammerstone\Sidecar\Deployment;
use Hammerstone\Sidecar\Package;
use Hammerstone\Sidecar\Results\SettledResult;
use Hammerstone\Sidecar\Sidecar;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use ParaTest\Logging\JUnit\Reader;
use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use Pest\Parallel\Contracts\RunningTest;
use Pest\Parallel\Serverless\Sidecar\Functions\RunTest;
use Pest\Parallel\Support\OutputHandler;
use Pest\Parallel\Support\PendingTestDetail;
use Pest\Parallel\Support\ProcessEnvironmentHandler;
use PHPUnit\TextUI\TestRunner;
use Symfony\Component\Console\Output\OutputInterface;

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

    /**
     * Tests that are currently running. Note that each
     * lambda function can be given multiple tests to run.
     *
     * @var array<int, array<ExecutableTest>>
     */
    protected $running = [];

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
                    "storage/logs",
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
        /**
         * Feature tests tend to take longer than unit tests, so if all feature
         * tests run together, that will increase the duration of the suite.
         * To help avoid that, we'll randomise the test order.
         */
        shuffle($this->pending);

        $availableTokens = range(1, $this->options->processes());

        Each::of(
            $this->yieldPromises($availableTokens),
            Closure::fromCallable([$this, 'tearDownTests']),
            Closure::fromCallable([$this, 'tearDownTests'])
        )->wait();
    }

    /**
     * @param array<int, int> $availableTokens
     */
    protected function yieldPromises(array $availableTokens): iterable
    {
        $chunkedTests = array_chunk(
            $this->pending,
            intval(ceil(count($this->pending) / count($availableTokens)))
        );

        foreach ($chunkedTests as $chunkIndex => $tests) {
            $token = $availableTokens[$chunkIndex];

            $this->running[$token] = $tests;

            foreach ($tests as $testIndex => $test) {
                unset($this->pending[$testIndex]);
            }

            $chunkTestCount = count($tests);
            $this->output->writeln("[$token] {$chunkTestCount} test files will be run...");

            $pendingTestDetails = array_map(function (ExecutableTest $test) use ($token) {
                return new PendingTestDetail($test, $this->options, $token);
            }, $this->running[$token]);

            yield $this->createRunningTests(...$pendingTestDetails);
        }
    }

    protected function createRunningTests(PendingTestDetail ...$pendingTestDetails): PromiseInterface
    {
        $testDetails = array_map(function (PendingTestDetail $pendingTestDetail) {
            return $this->createRunningTest($pendingTestDetail);
        }, $pendingTestDetails);

        $pendingResult = Sidecar::executeAsync(RunTest::class, [
            'tests' => $testDetails,
            'localCwd' => $this->options->cwd(),
            'filesToDownload' => $this->uploadedFiles,
            'timeout' => 5000,
        ]);

        return $pendingResult->rawPromise();
    }

    protected function createRunningTest(PendingTestDetail $pendingTestDetail): array
    {
        $passthruPhp = $this->options->passthruPhp() ?: [];

        $testCommand = $pendingTestDetail->getExecutableTest()->commandArguments(
            'vendor/bin/pest',
            $this->options->filtered(),
            $this->options->passthru(),
        );

        $args = array_merge($passthruPhp, $testCommand);
        $printerIndex = array_search('--printer', $args, true);
        unset($args[$printerIndex]);
        unset($args[$printerIndex + 1]);

        $tempFile = $pendingTestDetail->getExecutableTest()->getTempFile();

        $tempFileForLambda = str_replace(
            substr($tempFile, 0, strrpos($tempFile, '/')),
            '/tmp/junit/',
            $tempFile
        );

        $junitIndex = array_search('--log-junit', $args, true);
        $args[$junitIndex + 1] = $tempFileForLambda;

        return [
            'testCommand' => $args,
            'env' => array_merge(
                $this->options->fillEnvWithTokens($pendingTestDetail->getToken()),
                (new ProcessEnvironmentHandler())->getTokens()
            ),
            'tempFile' => $tempFileForLambda,
        ];
    }

    protected function tearDownTests(Result $result, int $testIndex): void
    {
        $token = $testIndex + 1;
        $tests = $this->running[$token];
        $result = (new SettledResult($result, new RunTest()))->throw();

        ray($result->body());
        ray($result->logs());

        foreach ($tests as $index => $test) {
            $details = $result->body()[$index];

            file_put_contents($test->getTempFile(), $details['junit']);
            $this->getInterpreter()->addReader(new Reader($test->getTempFile()));

            $this->outputHandler->handle(join(PHP_EOL, $details['output']));

            $this->exitcode = max($this->exitcode, $details['code']);
        }

        if ($this->shouldStopOnFailure() && $this->getExitCode() > TestRunner::SUCCESS_EXIT) {
            $this->pending = [];
        }

        unset($this->running[$token]);
    }
}
