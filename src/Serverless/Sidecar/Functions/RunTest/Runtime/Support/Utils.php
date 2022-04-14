<?php

namespace Pest\Parallel\Serverless\Sidecar\Functions\RunTest\Runtime\Support;

use Aws\S3\S3Client;

final class Utils
{
    /**
     * @var S3Client
     */
    static private $s3;

    public static function lambdaRoot(): string
    {
        return $_ENV['LAMBDA_TASK_ROOT'];
    }

    /**
     * @return array{"tests": array{"testCommand": array<mixed>, "env": array<mixed>, "tempFile": string}, "localCwd": string, "filesToDownload": array<string>}
     */
    public static function payload(): array
    {
        return json_decode($_SERVER['argv'][1], true);
    }

    public static function s3(): S3Client
    {
        if (static::$s3) {
            return static::$s3;
        }

        static::$s3 = new S3Client([
            'version' => 'latest',
            'region' => $_ENV['AWS_REGION'],
            'credentials' => [
                'key' => $_ENV['AWS_ACCESS_KEY_ID'],
                'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
                'token' => $_ENV['AWS_SESSION_TOKEN'],
            ],
        ]);

        static::$s3->registerStreamWrapper();

        return static::$s3;
    }
}
