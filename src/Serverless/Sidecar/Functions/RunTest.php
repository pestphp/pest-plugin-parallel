<?php

namespace Pest\Parallel\Serverless\Sidecar\Functions;

use Hammerstone\Sidecar\LambdaFunction;
use Hammerstone\Sidecar\Package;
use Hammerstone\Sidecar\Runtime;
use Hammerstone\Sidecar\WarmingConfig;
use Pest\TestSuite;

final class RunTest extends LambdaFunction
{
    public function runtime(): string
    {
        return Runtime::PROVIDED_AL2;
    }

    public function handler(): string
    {
        return 'noop';
    }

    public function package(): Package
    {
        return Package::make()->includeExactly([
            // Place the Runtime files at the root of the lambda function.
            __DIR__ . '/RunTest/Runtime' => '/',
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function layers(): array
    {
        return [
            'arn:aws:lambda:eu-west-1:959512994844:layer:vapor-php-81al2:2',
            'arn:aws:lambda:eu-west-1:674573131681:layer:aws-php-sdk-3_219_0:1'
        ];
    }

    public function timeout(): int
    {
        return 300;
    }

    /**
     * @return array<string, mixed>
     */
    public function variables(): array
    {
        return [
            'PATH' => '/opt/bin:/usr/local/bin:/usr/bin/:/bin:/usr/local/sbin',
            'LD_LIBRARY_PATH' => '/opt/lib:/opt/lib/bref:/lib64:/usr/lib64:/var/runtime:/var/runtime/lib:/var/task:/var/task/lib',
        ];
    }

    public function warmingConfig(): WarmingConfig
    {
        return WarmingConfig::instances(2);
    }
}
