<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Util;

use function array_slice;
use function array_unshift;
use function str_contains;
use function str_starts_with;

use Throwable;
use WebProject\Codeception\Module\AiReporter\Report\PathNormalizer;

/**
 * @phpstan-import-type TraceFrame from \WebProject\Codeception\Module\AiReporter\Report\ReportTypes
 */
final class TraceFrameProcessor
{
    public function __construct(
        private readonly PathNormalizer $pathNormalizer,
        private readonly int $maxFrames,
    ) {
    }

    /**
     * Build a cleaned trace: prepend the origin (throw-site) frame, then strip
     * framework noise. The origin frame takes priority over budget — if it is
     * new, the last user frame may be dropped to stay within maxFrames.
     *
     * @param array<int, TraceFrame> $trace
     * @return array<int, TraceFrame>
     */
    public function prepare(Throwable $throwable, array $trace): array
    {
        $traceWithOrigin = $this->prependOriginFrame($throwable, $trace);
        return $this->removeNoiseFrames($traceWithOrigin);
    }

    /**
     * Prepend the exception's throw-site as a synthetic origin frame. If the
     * first trace frame already matches that location, it is kept as-is.
     * Otherwise the origin is unshifted and the result is sliced to maxFrames,
     * which may drop the last existing frame to make room.
     *
     * @param array<int, TraceFrame> $trace
     * @return array<int, TraceFrame>
     */
    public function prependOriginFrame(Throwable $throwable, array $trace): array
    {
        $originFile = $this->pathNormalizer->normalize($throwable->getFile());
        $originLine = $throwable->getLine();

        $origin = [
            'file' => $originFile,
            'line' => $originLine,
            'call' => '[throw] ' . $throwable::class,
        ];

        if (isset($trace[0])) {
            $sameLocation = ($trace[0]['file'] ?? null) === $origin['file']
                && ($trace[0]['line'] ?? null) === $origin['line'];
            if ($sameLocation) {
                return array_slice($trace, 0, $this->maxFrames);
            }
        }

        array_unshift($trace, $origin);

        return array_slice($trace, 0, $this->maxFrames);
    }

    /**
     * @param array<int, TraceFrame> $trace
     * @return array<int, TraceFrame>
     */
    public function removeNoiseFrames(array $trace): array
    {
        $filtered = [];
        foreach ($trace as $frame) {
            if ($this->isNoiseFrame($frame)) {
                continue;
            }
            $filtered[] = $frame;
        }

        if ($filtered === []) {
            return array_slice($trace, 0, $this->maxFrames);
        }

        return array_slice($filtered, 0, $this->maxFrames);
    }

    /**
     * @param TraceFrame $frame
     */
    public function formatFrame(array $frame): string
    {
        $location = (string)($frame['file'] ?? '[internal]');
        if (isset($frame['line'])) {
            $location .= ':' . (string)$frame['line'];
        }

        $call = (string)($frame['call'] ?? '');

        return $call === '' ? $location : $location . ' ' . $call;
    }

    /**
     * @param TraceFrame $frame
     */
    private function isNoiseFrame(array $frame): bool
    {
        $file = (string)($frame['file'] ?? '');
        $call = (string)($frame['call'] ?? '');

        if ($file !== '' && !$this->isFrameworkFile($file)) {
            return false;
        }

        if ($file !== '' && $this->isFrameworkFile($file)) {
            return true;
        }

        return str_starts_with($call, '[throw] PHPUnit\\Framework\\')
            || str_starts_with($call, '[throw] Codeception\\')
            || str_contains($call, 'PHPUnit\\Framework\\Constraint\\')
            || str_contains($call, 'Codeception\\');
    }

    private function isFrameworkFile(string $file): bool
    {
        return str_starts_with($file, 'vendor/phpunit/')
            || str_starts_with($file, 'vendor/codeception/')
            || str_starts_with($file, 'vendor/composer/')
            || str_starts_with($file, 'tests/Support/_generated/')
            || str_contains($file, '/vendor/phpunit/')
            || str_contains($file, '/vendor/codeception/')
            || str_contains($file, '/vendor/composer/')
            || str_contains($file, '/tests/Support/_generated/');
    }
}
