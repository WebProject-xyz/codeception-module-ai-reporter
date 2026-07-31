<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Report;

use function array_slice;
use function array_unshift;
use function count;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function str_contains;
use function str_starts_with;
use Throwable;

/**
 * @phpstan-import-type TraceFrame from ReportTypes
 */
final class TraceNormalizer
{
    private const FRAMEWORK_PATHS = [
        'vendor/phpunit/',
        'vendor/codeception/',
        'vendor/composer/',
        'tests/Support/_generated/',
    ];

    public function __construct(
        private readonly PathNormalizer $pathNormalizer,
        private readonly int $maxFrames,
    ) {
    }

    /**
     * @return array<int, TraceFrame>
     */
    public function normalize(Throwable $throwable): array
    {
        $trace = $this->normalizeFromFrames($throwable->getTrace(), includeVendor: false);
        if ([] === $trace) {
            $trace = $this->normalizeFromFrames($throwable->getTrace(), includeVendor: true);
        }

        return $this->prepare($throwable, $trace);
    }

    /**
     * @param array<int, mixed> $frames
     *
     * @return array<int, TraceFrame>
     */
    public function normalizeFromFrames(array $frames, bool $includeVendor = false): array
    {
        $result = [];
        $seen   = [];

        foreach ($frames as $frame) {
            if (!is_array($frame)) {
                continue;
            }

            $normalized = [];
            $file       = $frame['file'] ?? null;
            if (is_string($file) && '' !== $file) {
                if (!$includeVendor && $this->pathNormalizer->isVendorPath($file)) {
                    continue;
                }

                $normalizedFile = $this->pathNormalizer->normalize($file);
                if ('' !== $normalizedFile) {
                    $normalized['file'] = $normalizedFile;
                }
            }

            $line = $frame['line'] ?? null;
            if (is_int($line)) {
                $normalized['line'] = $line;
            }

            $call = $this->callSignature($frame);
            if (null !== $call) {
                $normalized['call'] = $call;
            }

            if ([] === $normalized) {
                continue;
            }

            $dedupeKey = ($normalized['file'] ?? '') . ':' . ($normalized['line'] ?? 0) . ':' . ($normalized['call'] ?? '');
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $result[] = $normalized;
            if (count($result) >= $this->maxFrames) {
                break;
            }
        }

        return $result;
    }

    /**
     * Build a cleaned trace: prepend the origin (throw-site) frame, then strip
     * framework noise. The origin frame takes priority over budget — if it is
     * new, the last user frame may be dropped to stay within maxFrames.
     *
     * @param array<int, TraceFrame> $trace
     *
     * @return array<int, TraceFrame>
     */
    public function prepare(Throwable $throwable, array $trace): array
    {
        return $this->removeNoiseFrames($this->prependOriginFrame($throwable, $trace));
    }

    /**
     * @param array<int, TraceFrame> $trace
     *
     * @return array<int, TraceFrame>
     */
    public function removeNoiseFrames(array $trace): array
    {
        $filtered = [];
        foreach ($trace as $frame) {
            if (!$this->isNoiseFrame($frame)) {
                $filtered[] = $frame;
            }
        }

        return array_slice([] === $filtered ? $trace : $filtered, 0, $this->maxFrames);
    }

    /**
     * @param TraceFrame $frame
     */
    public function formatFrame(array $frame): string
    {
        $location = (string) ($frame['file'] ?? '[internal]');
        if (isset($frame['line'])) {
            $location .= ':' . (string) $frame['line'];
        }

        $call = (string) ($frame['call'] ?? '');

        return '' === $call ? $location : $location . ' ' . $call;
    }

    /**
     * Prepend the exception's throw-site as a synthetic origin frame. If the
     * first trace frame already matches that location, it is kept as-is.
     * Otherwise the origin is unshifted and the result is sliced to maxFrames,
     * which may drop the last existing frame to make room.
     *
     * @param array<int, TraceFrame> $trace
     *
     * @return array<int, TraceFrame>
     */
    private function prependOriginFrame(Throwable $throwable, array $trace): array
    {
        $origin = [
            'file' => $this->pathNormalizer->normalize($throwable->getFile()),
            'line' => $throwable->getLine(),
            'call' => '[throw] ' . $throwable::class,
        ];

        $sameLocation = isset($trace[0])
            && ($trace[0]['file'] ?? null) === $origin['file']
            && ($trace[0]['line'] ?? null) === $origin['line'];

        if (!$sameLocation) {
            array_unshift($trace, $origin);
        }

        return array_slice($trace, 0, $this->maxFrames);
    }

    /**
     * @param TraceFrame $frame
     */
    private function isNoiseFrame(array $frame): bool
    {
        $file = (string) ($frame['file'] ?? '');
        if ('' !== $file) {
            return $this->isFrameworkFile($file);
        }

        $call = (string) ($frame['call'] ?? '');

        return str_starts_with($call, '[throw] PHPUnit\\Framework\\')
            || str_contains($call, 'PHPUnit\\Framework\\Constraint\\')
            || str_contains($call, 'Codeception\\');
    }

    private function isFrameworkFile(string $file): bool
    {
        foreach (self::FRAMEWORK_PATHS as $needle) {
            if (str_contains($file, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<array-key, mixed> $frame */
    private function callSignature(array $frame): ?string
    {
        $parts = [];

        $class = $frame['class'] ?? null;
        if (is_string($class) && '' !== $class) {
            $parts[] = $class;
        }

        $type = $frame['type'] ?? null;
        if (is_string($type) && '' !== $type) {
            $parts[] = $type;
        }

        $function = $frame['function'] ?? null;
        if (is_string($function) && '' !== $function) {
            $parts[] = $function;
        }

        if ([] === $parts) {
            return null;
        }

        return implode('', $parts);
    }
}
