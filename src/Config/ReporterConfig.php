<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Config;

use function in_array;
use InvalidArgumentException;
use function is_bool;
use function is_int;
use function is_string;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_starts_with;

/**
 * @phpstan-type RawConfig array{
 *     format?: self::FORMAT_*,
 *     output?: string,
 *     max_frames?: int<1, max>,
 *     include_steps?: bool,
 *     include_artifacts?: bool,
 *     compact_paths?: bool
 * }
 */
final class ReporterConfig
{
    public const FORMAT_TEXT = 'text';
    public const FORMAT_JSON = 'json';
    public const FORMAT_BOTH = 'both';

    private const DEFAULT_MAX_FRAMES = 8;

    /**
     * @param self::FORMAT_*   $format
     * @param non-empty-string $outputDir
     * @param int<1, max>      $maxFrames
     */
    private function __construct(
        private readonly string $format,
        private readonly string $outputDir,
        private readonly int $maxFrames,
        private readonly bool $includeSteps,
        private readonly bool $includeArtifacts,
        private readonly bool $compactPaths,
    ) {
    }

    /**
     * @param RawConfig        $raw
     * @param non-empty-string $defaultOutputDir
     */
    public static function fromArray(array $raw, string $defaultOutputDir, string $projectRoot): self
    {
        /** @var self::FORMAT_* $format */
        $format = $raw['format'] ?? self::FORMAT_BOTH;
        // @phpstan-ignore-next-line
        if (!is_string($format) || !in_array($format, [self::FORMAT_TEXT, self::FORMAT_JSON, self::FORMAT_BOTH], true)) {
            throw new InvalidArgumentException('Invalid `format`; expected one of: text, json, both.');
        }

        $output = $raw['output'] ?? $defaultOutputDir;
        if ('' === $output) {
            $output = $defaultOutputDir;
        }
        // @phpstan-ignore-next-line
        if (!is_string($output) || '' === $output) {
            throw new InvalidArgumentException('Invalid `output`; expected a non-empty directory path.');
        }

        $outputDir = self::resolvePath($output, $projectRoot);

        /** @var int<1, max> $maxFrames */
        $maxFrames = $raw['max_frames'] ?? self::DEFAULT_MAX_FRAMES;
        // @phpstan-ignore-next-line
        if (!is_int($maxFrames) || $maxFrames < 1) {
            throw new InvalidArgumentException('Invalid `max_frames`; expected a positive integer.');
        }

        $includeSteps = $raw['include_steps'] ?? true;
        // @phpstan-ignore-next-line
        if (!is_bool($includeSteps)) {
            throw new InvalidArgumentException('Invalid `include_steps`; expected boolean.');
        }

        $includeArtifacts = $raw['include_artifacts'] ?? true;
        // @phpstan-ignore-next-line
        if (!is_bool($includeArtifacts)) {
            throw new InvalidArgumentException('Invalid `include_artifacts`; expected boolean.');
        }

        $compactPaths = $raw['compact_paths'] ?? true;
        // @phpstan-ignore-next-line
        if (!is_bool($compactPaths)) {
            throw new InvalidArgumentException('Invalid `compact_paths`; expected boolean.');
        }

        /** @var non-empty-string $outputDir */
        $outputDir = rtrim($outputDir, '/\\');

        return new self(
            format: $format,
            outputDir: $outputDir,
            maxFrames: $maxFrames,
            includeSteps: $includeSteps,
            includeArtifacts: $includeArtifacts,
            compactPaths: $compactPaths,
        );
    }

    public function wantsJson(): bool
    {
        return self::FORMAT_JSON === $this->format || self::FORMAT_BOTH === $this->format;
    }

    public function wantsText(): bool
    {
        return self::FORMAT_TEXT === $this->format || self::FORMAT_BOTH === $this->format;
    }

    /** @return non-empty-string */
    public function outputDir(): string
    {
        return $this->outputDir;
    }

    /** @return int<1, max> */
    public function maxFrames(): int
    {
        return $this->maxFrames;
    }

    public function includeSteps(): bool
    {
        return $this->includeSteps;
    }

    public function includeArtifacts(): bool
    {
        return $this->includeArtifacts;
    }

    public function compactPaths(): bool
    {
        return $this->compactPaths;
    }

    private static function resolvePath(string $path, string $projectRoot): string
    {
        if (self::isAbsolutePath($path)) {
            return $path;
        }

        return sprintf('%s/%s', rtrim($projectRoot, '/\\'), $path);
    }

    private static function isAbsolutePath(string $path): bool
    {
        if ('' === $path) {
            return false;
        }

        if ('/' === $path[0]) {
            return true;
        }

        if (str_starts_with($path, '\\\\')) {
            return true;
        }

        return 1 === preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }
}
