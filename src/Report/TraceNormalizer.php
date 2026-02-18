<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Report;

use function count;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use Throwable;

/**
 * @phpstan-import-type TraceFrame from ReportTypes
 */
final class TraceNormalizer
{
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

        return $trace;
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
