<?php

declare(strict_types=1);

namespace Pest\Parallel\Concerns\Arguments;

trait ManagesArguments
{
    /**
     * @var array<int, string>
     */
    protected $arguments;

    final public function handle(array $arguments): array
    {
        $this->arguments = $arguments;
        $this->editArguments();

        return $this->arguments;
    }

    abstract protected function editArguments(): void;

    /**
     * Add an argument and an optional value to the given arguments.
     */
    private function setArgument(string $key, string $value = ''): self
    {
        $argument = $key;

        if ($value !== '') {
            $argument .= "={$value}";
        }

        $this->arguments[] = $argument;

        return $this;
    }

    /**
     * Remove the given argument from the array if it exists.
     */
    private function unsetArgument(string $argument): self
    {
        $this->arguments = array_filter($this->arguments, function ($value) use ($argument): bool {
            return strpos($value, $argument) !== 0;
        });

        return $this;
    }
}
