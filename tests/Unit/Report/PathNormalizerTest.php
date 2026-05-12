<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Unit\Report;

use Codeception\Test\Unit;
use WebProject\Codeception\Module\AiReporter\Tests\Support\Fixture\PathNormalizerFactory;

final class PathNormalizerTest extends Unit
{
    public function testCompactsProjectRelativePath(): void
    {
        $normalizer = PathNormalizerFactory::make();

        self::assertSame('src/Extension/AiReporter.php', $normalizer->normalize('/repo/project/src/Extension/AiReporter.php'));
    }

    public function testLeavesAbsolutePathWhenCompactionDisabled(): void
    {
        $normalizer = PathNormalizerFactory::make(false);

        self::assertSame('/repo/project/src/Extension/AiReporter.php', $normalizer->normalize('/repo/project/src/Extension/AiReporter.php'));
    }

    public function testVendorPathDetection(): void
    {
        $normalizer = PathNormalizerFactory::make();

        self::assertTrue($normalizer->isVendorPath('/repo/project/vendor/package/file.php'));
        self::assertFalse($normalizer->isVendorPath('/repo/project/src/file.php'));
    }

    public function testCompactsWindowsPathAndNormalizesSeparators(): void
    {
        $normalizer = PathNormalizerFactory::make(true, 'C:\\repo\\project');

        self::assertSame(
            'src/Extension/AiReporter.php',
            $normalizer->normalize('c:\\repo\\project\\src\\Extension\\AiReporter.php')
        );
    }

    public function testVendorPathDetectionForWindowsStylePaths(): void
    {
        $normalizer = PathNormalizerFactory::make(true, 'C:\\repo\\project');

        self::assertTrue($normalizer->isVendorPath('C:\\repo\\project\\vendor\\package\\file.php'));
        self::assertFalse($normalizer->isVendorPath('C:\\repo\\project\\src\\file.php'));
    }
}
