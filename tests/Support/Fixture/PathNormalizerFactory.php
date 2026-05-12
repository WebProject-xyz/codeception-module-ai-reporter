<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Support\Fixture;

use WebProject\Codeception\Module\AiReporter\Report\PathNormalizer;

final class PathNormalizerFactory
{
    public static function make(bool $compactPaths = true, string $projectRoot = '/repo/project'): PathNormalizer
    {
        return new PathNormalizer($projectRoot, $compactPaths);
    }
}
