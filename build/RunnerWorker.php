<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

use Closure;
use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\WorkerCrashedException;
use RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * This is a copy of Paratest's Runner Worker with a few minor tweaks:
 * 1) We make the $process property public
 * 2) We add an $argEditor Closure to the constructor as a hook for using the Pest runner.
 *
 * @internal
 *
 * @see \ParaTest\Runners\PHPUnit\Worker\RunnerWorker
 */
final class RunnerWorker
{
    /**
     * @var ExecutableTest
     */
    private $executableTest;

    /**
     * @var Process<mixed>
     */
    public $process;

    public function __construct(ExecutableTest $executableTest, Options $options, int $token, Closure $argEditor)
    {
        $this->executableTest = $executableTest;

        $phpFinder = new PhpExecutableFinder();
        $args      = [$phpFinder->find(false)];
        $args      = array_merge($args, $phpFinder->findArguments());

        if (($passthruPhp = $options->passthruPhp()) !== null) {
            $args = array_merge($args, $passthruPhp);
        }

        $args = array_merge(
            $args,
            $this->executableTest->commandArguments(
                $options->phpunit(),
                $options->filtered(),
                $options->passthru()
            )
        );

        $args = $argEditor($args, $options);

        $this->process = new Process($args, $options->cwd(), $options->fillEnvWithTokens($token));

        $cmd = $this->process->getCommandLine();
        $this->assertValidCommandLineLength($cmd);
        $this->executableTest->setLastCommand($cmd);
    }

    public function getExecutableTest(): ExecutableTest
    {
        return $this->executableTest;
    }

    /**
     * Executes the test by creating a separate process.
     */
    public function run(): void
    {
        $this->process->start();
    }

    /**
     * Check if the process has terminated.
     */
    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    /**
     * Stop the process and return it's exit code.
     */
    public function stop(): ?int
    {
        return $this->process->stop();
    }

    /**
     * Assert that command line length is valid.
     *
     * In some situations process command line can became too long when combining different test
     * cases in single --filter arguments so it's better to show error regarding that to user
     * and propose him to decrease max batch size.
     *
     * @param string $cmd Command line
     *
     * @throws RuntimeException on too long command line
     *
     * @codeCoverageIgnore
     */
    private function assertValidCommandLineLength(string $cmd): void
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            return;
        }

        // symfony's process wrapper
        $cmd = 'cmd /V:ON /E:ON /C "(' . $cmd . ')';
        if (strlen($cmd) > 32767) {
            throw new RuntimeException('Command line is too long, try to decrease max batch size');
        }
    }

    /**
     * @phpstan-ignore-next-line
     */
    public function getWorkerCrashedException(?Throwable $previousException = null): WorkerCrashedException
    {
        return WorkerCrashedException::fromProcess(
            $this->process,
            $this->process->getCommandLine(),
            $previousException
        );
    }
}
