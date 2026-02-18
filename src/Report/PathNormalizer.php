<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Report;

use function ltrim;
use function rtrim;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;

final class PathNormalizer
{
    private string $normalizedRoot;
    private string $normalizedRootLower;

    public function __construct(string $projectRoot, private readonly bool $compactPaths)
    {
        $normalized                  = str_replace('\\', '/', rtrim($projectRoot, '/\\'));
        $this->normalizedRoot        = $normalized . '/';
        $this->normalizedRootLower   = strtolower($this->normalizedRoot);
    }

    /**
     * @phpstan-return ($path is null ? null : string)
     */
    public function normalize(?string $path): ?string
    {
        if (null === $path) {
            return null;
        }

        if ('' === $path) {
            return '';
        }

        $normalized = str_replace('\\', '/', $path);
        if (!$this->compactPaths) {
            return $normalized;
        }

        if ($this->startsWithProjectRoot($normalized)) {
            return ltrim((string) substr($normalized, strlen($this->normalizedRoot)), '/');
        }

        return $normalized;
    }

    public function isVendorPath(?string $path): bool
    {
        if (null === $path || '' === $path) {
            return false;
        }

        $normalized = strtolower(str_replace('\\', '/', $path));

        return str_contains($normalized, '/vendor/');
    }

    private function startsWithProjectRoot(string $path): bool
    {
        return str_starts_with($path, $this->normalizedRoot)
            || str_starts_with(strtolower($path), $this->normalizedRootLower);
    }
}
