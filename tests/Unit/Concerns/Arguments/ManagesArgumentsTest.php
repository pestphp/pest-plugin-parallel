<?php

declare(strict_types=1);

use Pest\Parallel\Concerns\Arguments\ManagesArguments;

$argumentManager = new class
{
    use ManagesArguments;

    protected function editArguments(): void
    {
        $this->setArgument('foo', 'bar');
        $this->unsetArgument('baz');
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function argumentExists(string $key): bool
    {
        return $this->hasArgument($key);
    }
};

it('can set an argument', function () use ($argumentManager) {
    $argumentManager->handle([]);

    expect($argumentManager->getArguments()[0])->toBe('foo=bar');
});

it('can unset an argument', function () use ($argumentManager) {
    $argumentManager->handle(['baz=boom']);

    expect($argumentManager->getArguments())
        ->toHaveCount(1)
        ->{1}->toBe('foo=bar');
});

it('can check for the existence of an argument', function () use ($argumentManager) {
    $argumentManager->handle([]);

    expect($argumentManager)
        ->argumentExists('foo')->toBeTrue()
        ->argumentExists('bar')->toBeFalse();
});
