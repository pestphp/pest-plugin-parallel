<?php

declare(strict_types=1);

use Aws\S3\S3Client;

$lambdaRoot = $_ENV['LAMBDA_TASK_ROOT'];

$payload = json_decode($_SERVER['argv'][1], true);

$s3Client = new S3Client([
    'version' => 'latest',
    'region' => $_ENV['AWS_REGION'],
    'credentials' => [
        'key' => $_ENV['AWS_ACCESS_KEY_ID'],
        'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
        'token' => $_ENV['AWS_SESSION_TOKEN'],
    ],
]);

$s3Client->registerStreamWrapper();

mkdir('/tmp/sidecar');
mkdir('/tmp/project');

foreach ($payload['filesToDownload'] as $filename => $s3Url) {
    $tmpLocation = "/tmp/{$filename}";

    if (file_exists($tmpLocation)) {
        continue;
    }

    copy($s3Url, $tmpLocation);

    $zip = new ZipArchive();
    $zip->open($tmpLocation);
    $zip->extractTo('/tmp/project');
    $zip->close();
}
