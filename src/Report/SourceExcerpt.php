<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Report;

use function count;
use function file;
use function is_file;
use function max;
use function min;
use function rtrim;
use function str_starts_with;

/**
 * @phpstan-import-type SourceContext from ReportTypes
 * @phpstan-import-type TraceFrame from ReportTypes
 */
final class SourceExcerpt
{
    private const LINES_AFTER = 2;

    public function __construct(
        private readonly string $projectRoot,
        private readonly int $contextLines,
    ) {
    }

    /**
     * Read the code around the first project frame of a trace. Vendor frames are
     * skipped: their source says nothing about the failing code.
     *
     * @param array<int, TraceFrame> $trace
     *
     * @return SourceContext|null
     */
    public function forTrace(array $trace): ?array
    {
        if ($this->contextLines < 1) {
            return null;
        }

        foreach ($trace as $frame) {
            $file = $frame['file'] ?? null;
            $line = $frame['line'] ?? null;
            if (null === $file || null === $line || str_starts_with($file, 'vendor/')) {
                continue;
            }

            $excerpt = $this->read($file, $line);
            if (null !== $excerpt) {
                return $excerpt;
            }
        }

        return null;
    }

    /**
     * @return SourceContext|null
     */
    private function read(string $file, int $line): ?array
    {
        $absolute = str_starts_with($file, '/') ? $file : rtrim($this->projectRoot, '/') . '/' . $file;
        if (!is_file($absolute)) {
            return null;
        }

        $source = file($absolute);
        if (false === $source || [] === $source) {
            return null;
        }

        // Asymmetric on purpose: the lines above a failing line hold the setup
        // that names the subject under test, the lines below are usually the
        // closing brace and the next method signature.
        $start = max(1, $line - $this->contextLines);
        $end   = min(count($source), $line + min(self::LINES_AFTER, $this->contextLines));
        if ($line > count($source)) {
            return null;
        }

        $lines = [];
        for ($current = $start; $current <= $end; ++$current) {
            $lines[] = rtrim($source[$current - 1], "\r\n");
        }

        return [
            'file'       => $file,
            'line'       => $line,
            'start_line' => $start,
            'lines'      => $lines,
        ];
    }
}
