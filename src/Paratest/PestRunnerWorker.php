<?php

declare(strict_types=1);

namespace Pest\Parallel\Paratest;

use InvalidArgumentException;
use NunoMaduro\Collision\Adapters\Phpunit\Printer;
use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\Worker\NullPhpunitPrinter;
use ParaTest\Runners\PHPUnit\Worker\RunnerWorker;
use function strlen;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 *
 * @phpstan-ignore-next-line
 */
final class PestRunnerWorker extends RunnerWorker
{
    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var array<string>
     */
    public static $additionalOutput = [];

    public function __construct(OutputInterface $output, ExecutableTest $executableTest, Options $options, int $token)
    {
        parent::__construct($executableTest, $options, $token);
        $this->output = $output;
    }

    /**
     * @phpstan-ignore-next-line
     */
    public function stop(): ?int
    {
        $exitCode = parent::stop();
        $this->handleOutput($this->process->getOutput());

        return $exitCode;
    }

    private function handleOutput(string $output): void
    {
        if (strlen($output) === 0) {
            return;
        }

        try {
            preg_match_all('/^\\n/m', $output, $matches, PREG_OFFSET_CAPTURE);

            $this->output->write(substr($output, 0, $matches[0][1][1]));

            if (count($matches[0]) > 3) {
                $summarySectionIndex = count($matches[0]) - 2;

                static::$additionalOutput[] = substr(
                    $output,
                    $matches[0][1][1],
                    $matches[0][$summarySectionIndex][1] - $matches[0][1][1],
                );
            }
        } catch (InvalidArgumentException $exception) {
            $this->output->write($output);
        }
    }

    /**
     * @param array<int, string> $args
     *
     * @return array<int, string>
     */
    public function editArgs(array $args, Options $options): array
    {
        $phpUnitIndex        = array_search($options->phpunit(), $args, true);
        $args[$phpUnitIndex] = $this->getPestBinary($options);

        $printerIndex        = array_search(NullPhpunitPrinter::class, $args, true);
        $args[$printerIndex] = Printer::class;

        return $args;
    }

    private function getPestBinary(Options $options): string
    {
        $paths = [
            implode(DIRECTORY_SEPARATOR, [$options->cwd(), 'bin', 'pest']),
            implode(DIRECTORY_SEPARATOR, [$options->cwd(), 'vendor', 'bin', 'pest']),
        ];

        return file_exists($paths[0]) ? $paths[0] : $paths[1];
    }
}
