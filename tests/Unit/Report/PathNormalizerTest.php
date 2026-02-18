<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Unit\Report;

use Codeception\Test\Unit;
use WebProject\Codeception\Module\AiReporter\Report\PathNormalizer;

final class PathNormalizerTest extends Unit
{
    public function testCompactsProjectRelativePath(): void
    {
        $normalizer = new PathNormalizer('/repo/project', true);

        self::assertSame('src/Extension/AiReporter.php', $normalizer->normalize('/repo/project/src/Extension/AiReporter.php'));
    }

    public function testLeavesAbsolutePathWhenCompactionDisabled(): void
    {
        $normalizer = new PathNormalizer('/repo/project', false);

        self::assertSame('/repo/project/src/Extension/AiReporter.php', $normalizer->normalize('/repo/project/src/Extension/AiReporter.php'));
    }

    public function testVendorPathDetection(): void
    {
        $normalizer = new PathNormalizer('/repo/project', true);

        self::assertTrue($normalizer->isVendorPath('/repo/project/vendor/package/file.php'));
        self::assertFalse($normalizer->isVendorPath('/repo/project/src/file.php'));
    }

    public function testCompactsWindowsPathAndNormalizesSeparators(): void
    {
        $normalizer = new PathNormalizer('C:\\repo\\project', true);

        self::assertSame(
            'src/Extension/AiReporter.php',
            $normalizer->normalize('c:\\repo\\project\\src\\Extension\\AiReporter.php')
        );
    }

    public function testVendorPathDetectionForWindowsStylePaths(): void
    {
        $normalizer = new PathNormalizer('C:\\repo\\project', true);

        self::assertTrue($normalizer->isVendorPath('C:\\repo\\project\\vendor\\package\\file.php'));
        self::assertFalse($normalizer->isVendorPath('C:\\repo\\project\\src\\file.php'));
    }
}
