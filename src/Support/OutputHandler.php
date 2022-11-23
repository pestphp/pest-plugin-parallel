<?php

declare(strict_types=1);

namespace Pest\Parallel\Support;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class OutputHandler
{
    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * A static store for any output to be presented after
     * all tests have run.
     *
     * @var array<string>
     */
    public static $additionalOutput = [];

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function handle(string $content): void
    {
        if ($content === '') {
            return;
        }

        if (strpos($content, 'No tests executed!') !== false) {
            return;
        }

        try {
            $this->standardOutput($content);
        } catch (\Throwable $exception) { // @phpstan-ignore-line
            $this->output->write($content);
        }
    }

    private function standardOutput(string $content): void
    {
        preg_match_all('/^\\n/m', $content, $matches, PREG_OFFSET_CAPTURE);

        $this->output->write(substr($content, 0, $matches[0][1][1]));

        if (count($matches[0]) > 3) {
            $summarySectionIndex = count($matches[0]) - 2;

            self::$additionalOutput[] = substr(
                $content,
                $matches[0][1][1],
                $matches[0][$summarySectionIndex][1] - $matches[0][1][1],
            );
        }
    }

    /**
     * Clear all stored output.
     */
    public static function reset(): void
    {
        self::$additionalOutput = [];
    }
}
