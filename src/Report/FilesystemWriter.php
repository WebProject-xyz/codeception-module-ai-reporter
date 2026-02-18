<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Report;

use function dirname;
use function file_put_contents;
use function is_dir;
use function mkdir;
use RuntimeException;
use function sprintf;

final class FilesystemWriter
{
    public function write(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create output directory: %s', $directory));
        }

        if (false === file_put_contents($path, $contents)) {
            throw new RuntimeException(sprintf('Unable to write report file: %s', $path));
        }
    }
}
