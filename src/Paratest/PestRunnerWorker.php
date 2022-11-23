<?php

declare(strict_types=1);

namespace Pest\Parallel\Paratest;

use NunoMaduro\Collision\Adapters\Phpunit\Printer;
use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\Worker\NullPhpunitPrinter;
use ParaTest\Runners\PHPUnit\Worker\RunnerWorker;
use ParaTest\Runners\PHPUnit\WorkerCrashedException;
use Pest\Parallel\Support\OutputHandler;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * @internal
 *
 * @mixin RunnerWorker
 */
final class PestRunnerWorker
{
    /**
     * It must be a 1 byte string to ensure
     * filesize() is equal to the number of tests executed.
     */
    public const TEST_EXECUTED_MARKER = '1';

    public const COMMAND_EXIT = "EXIT\n";

    /** @var ExecutableTest|null */
    private $currentlyExecuting;
    /** @var Process */
    private $process;
    /** @var int */
    private $inExecution = 0;
    /** @var OutputInterface */
    private $output;
    /** @var string[] */
    public $commands = [];
    /** @var string */
    private $writeToPathname;
    /** @var InputStream */
    private $input;

    public function __construct(OutputInterface $output, Options $options, int $token)
    {
        $wrapper = self::getPestWrapperBinary($options);

        $this->output = $output;

        $this->writeToPathname = sprintf(
            '%s%sworker_%s_stdout_%s',
            $options->tmpDir(),
            DIRECTORY_SEPARATOR,
            $token,
            uniqid(),
        );
        touch($this->writeToPathname);

        $phpFinder = new PhpExecutableFinder();
        $phpBin    = $phpFinder->find(false);
        assert($phpBin !== false);
        $parameters = [$phpBin];
        $parameters = array_merge($parameters, $phpFinder->findArguments());

        if (($passthruPhp = $options->passthruPhp()) !== null) {
            $parameters = array_merge($parameters, $passthruPhp);
        }

        $parameters[] = $wrapper;
        $parameters[] = '--write-to';
        $parameters[] = $this->writeToPathname;

        if ($options->debug()) {
            $this->handleOutput(sprintf(
                "Starting WrapperWorker via: %s\n",
                implode(' ', array_map('\escapeshellarg', $parameters)),
            ));
        }

        $this->input   = new InputStream();
        $this->process = new Process(
            $parameters,
            $options->cwd(),
            $options->fillEnvWithTokens($token),
            $this->input,
            null,
        );
    }

    public function __destruct()
    {
        // @phpstan-ignore-next-line
        @unlink($this->writeToPathname);
    }

    public function start(): void
    {
        $this->process->start();
    }

    // @phpstan-ignore-next-line
    public function getWorkerCrashedException(?\Throwable $previousException = null): WorkerCrashedException
    {
        $command = end($this->commands);
        assert($command !== false);

        return WorkerCrashedException::fromProcess($this->process, $command, $previousException);
    }

    /** @param array<string, string|null> $phpunitOptions */
    public function assign(ExecutableTest $test, string $phpunit, array $phpunitOptions, Options $options): void
    {
        assert($this->currentlyExecuting === null);
        $commandArguments = $test->commandArguments($phpunit, $phpunitOptions, $options->passthru());
        $commandArguments = self::editArgs($commandArguments, $options);
        $command          = implode(' ', array_map('\\escapeshellarg', $commandArguments));

        if ($options->debug()) {
            $this->handleOutput("\nExecuting test via: {$command}\n");
        }

        $this->input->write(serialize($commandArguments) . "\n");

        $this->currentlyExecuting = $test;
        $test->setLastCommand($command);
        $this->commands[] = $command;
        $this->inExecution++;
    }

    public function reset(): void
    {
        $this->currentlyExecuting = null;
    }

    public function printOutput(): void
    {
        $output = $this->process->getOutput();
        $this->handleOutput($output);
        $this->process->clearOutput();
    }

    public function stop(): void
    {
        $this->input->write(self::COMMAND_EXIT);
    }

    private function handleOutput(string $output): void
    {
        $handler = new OutputHandler($this->output);
        $handler->handle($output);
    }

    /**
     * @param array<int, string> $args
     *
     * @return array<int, string>
     */
    private static function editArgs(array $args, Options $options): array
    {
        $phpUnitIndex        = array_search($options->phpunit(), $args, true);
        $args[$phpUnitIndex] = static::getPestBinary($options);

        $printerIndex        = array_search(NullPhpunitPrinter::class, $args, true);
        $args[$printerIndex] = Printer::class;

        return $args;
    }

    private static function getPestWrapperBinary(Options $options): string
    {
        $paths = [
            implode(DIRECTORY_SEPARATOR, [$options->cwd(), 'bin', 'pest-wrapper.php']),
            implode(DIRECTORY_SEPARATOR, [$options->cwd(), 'vendor', 'pestphp', 'pest-plugin-prallel', 'bin', 'pest-wrapper.php']),
        ];

        return file_exists($paths[0]) ? $paths[0] : $paths[1];
    }

    private static function getPestBinary(Options $options): string
    {
        $paths = [
            implode(DIRECTORY_SEPARATOR, [$options->cwd(), 'bin', 'pest']),
            implode(DIRECTORY_SEPARATOR, [$options->cwd(), 'vendor', 'bin', 'pest']),
        ];

        return file_exists($paths[0]) ? $paths[0] : $paths[1];
    }

    public function hasCurrentlyExecuting(): bool
    {
        return $this->currentlyExecuting !== null;
    }

    public function getCoverageFileName(): string
    {
        assert($this->currentlyExecuting !== null);

        return $this->currentlyExecuting->getCoverageFileName();
    }

    public function isFree(): bool
    {
        clearstatcache(true, $this->writeToPathname);

        return $this->inExecution === filesize($this->writeToPathname);
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    public function getExecutableTest(): ExecutableTest
    {
        assert($this->currentlyExecuting !== null);

        return $this->currentlyExecuting;
    }
}
