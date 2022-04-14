<?php

declare(strict_types=1);

use Pest\Parallel\Serverless\Sidecar\Functions\RunTest\Runtime\Support\Utils;

/**
 * We'll call the s3 method to register a new stream wrapper.
 */
Utils::s3();

mkdir('/tmp/sidecar');
mkdir('/tmp/project');

foreach (Utils::payload()['filesToDownload'] as $filename => $s3Url) {
    $tmpLocation = "/tmp/{$filename}";

    // TODO: This needs to be more robust. If two PRs are outstanding, this could be somebody else's code.
    if (file_exists($tmpLocation)) {
        continue;
    }

    copy($s3Url, $tmpLocation);

    $zip = new ZipArchive();
    $zip->open($tmpLocation);
    $zip->extractTo('/tmp/project');
    $zip->close();
}

