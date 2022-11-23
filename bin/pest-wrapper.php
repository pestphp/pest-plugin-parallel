<?php

declare(strict_types=1);

use ParaTest\Runners\PHPUnit\Worker\WrapperWorker;
use Pest\Support\Container;
use Pest\TestSuite;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

function getRootPath(): string
{
    // Used when Pest is required using composer.
    $vendorPath = dirname(__DIR__, 4) . '/vendor/autoload.php';

    // Used when Pest maintainers are running Pest tests.
    $localPath = dirname(__DIR__) . '/vendor/autoload.php';

    if (file_exists($vendorPath)) {
        include_once $vendorPath;
        $autoloadPath = $vendorPath;
    } else {
        include_once $localPath;
        $autoloadPath = $localPath;
    }

    return dirname($autoloadPath, 2);
}

function getContainer(): Container
{
    $argv = new ArgvInput();

    /** @var string $testDirectory */
    $testDirectory = $argv->getParameterOption('--test-directory', 'tests');
    $testSuite     = TestSuite::getInstance(getRootPath(), $testDirectory);

    $isDecorated = $argv->getParameterOption('--colors', 'always') !== 'never';
    $output      = new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, $isDecorated);
    
    $container = Container::getInstance();
    $container->add(TestSuite::class, $testSuite);
    $container->add(OutputInterface::class, $output);

    return $container;
}

(static function (): void {
    $opts = getopt('', ['write-to:']);

    $composerAutoloadFiles = [
        dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'autoload.php',
        dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
    ];

    foreach ($composerAutoloadFiles as $file) {
        if (file_exists($file)) {
            require_once $file;
            define('PHPUNIT_COMPOSER_INSTALL', $file);

            break;
        }
    }

    assert(array_key_exists('write-to', $opts) && is_string($opts['write-to']));
    $writeTo = fopen($opts['write-to'], 'wb');
    assert(is_resource($writeTo));

    $container = getContainer();
    $i = 0;
    while (true) {
        $i++;
        if (feof(STDIN)) {
            exit;
        }

        $command = fgets(STDIN);
        if ($command === false || $command === WrapperWorker::COMMAND_EXIT) {
            exit;
        }

        /** @var array<int, string> $arguments */
        $arguments = unserialize($command);

        /** @var \Pest\Console\Command $cmd */
        $cmd = $container->get(\Pest\Console\Command::class);
        $cmd->run($arguments, false);
        
        fwrite($writeTo, WrapperWorker::TEST_EXECUTED_MARKER);
        fflush($writeTo);
    }
})();
