<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Config;

use function implode;
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
        $format = self::readEnum(
            $raw['format'] ?? null,
            'format',
            [self::FORMAT_TEXT, self::FORMAT_JSON, self::FORMAT_BOTH],
            self::FORMAT_BOTH,
        );

        $output = self::readOutput($raw['output'] ?? null, $defaultOutputDir);

        /** @var non-empty-string $outputDir */
        $outputDir = rtrim(self::resolvePath($output, $projectRoot), '/\\');

        return new self(
            format: $format,
            outputDir: $outputDir,
            maxFrames: self::readPositiveInt($raw['max_frames'] ?? null, 'max_frames', self::DEFAULT_MAX_FRAMES),
            includeSteps: self::readBool($raw['include_steps'] ?? null, 'include_steps', true),
            includeArtifacts: self::readBool($raw['include_artifacts'] ?? null, 'include_artifacts', true),
            compactPaths: self::readBool($raw['compact_paths'] ?? null, 'compact_paths', true),
        );
    }

    /**
     * @param list<string> $allowed
     */
    private static function readEnum(mixed $value, string $field, array $allowed, string $default): string
    {
        if (null === $value) {
            return $default;
        }
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('Invalid `%s`; expected one of: %s.', $field, implode(', ', $allowed)));
        }

        return $value;
    }

    /** @param non-empty-string $default */
    private static function readOutput(mixed $value, string $default): string
    {
        if (null === $value || '' === $value) {
            return $default;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('Invalid `output`; expected a non-empty directory path.');
        }

        return $value;
    }

    /**
     * @param int<1, max> $default
     *
     * @return int<1, max>
     */
    private static function readPositiveInt(mixed $value, string $field, int $default): int
    {
        if (null === $value) {
            return $default;
        }
        if (!is_int($value) || $value < 1) {
            throw new InvalidArgumentException(sprintf('Invalid `%s`; expected a positive integer.', $field));
        }

        return $value;
    }

    private static function readBool(mixed $value, string $field, bool $default): bool
    {
        if (null === $value) {
            return $default;
        }
        if (!is_bool($value)) {
            throw new InvalidArgumentException(sprintf('Invalid `%s`; expected boolean.', $field));
        }

        return $value;
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
