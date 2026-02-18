<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Unit\Report;

use Codeception\Test\Unit;
use WebProject\Codeception\Module\AiReporter\Report\PathNormalizer;
use WebProject\Codeception\Module\AiReporter\Report\TraceNormalizer;

final class TraceNormalizerTest extends Unit
{
    public function testFiltersVendorFramesByDefaultAndLimitsSize(): void
    {
        $pathNormalizer = new PathNormalizer('/repo/project', true);
        $normalizer     = new TraceNormalizer($pathNormalizer, 2);

        $frames = [
            [
                'file'     => '/repo/project/vendor/codeception/file.php',
                'line'     => 10,
                'class'    => 'Vendor\\Runner',
                'type'     => '::',
                'function' => 'run',
            ],
            [
                'file'     => '/repo/project/src/Service/Foo.php',
                'line'     => 20,
                'class'    => 'App\\Service\\Foo',
                'type'     => '->',
                'function' => 'execute',
            ],
            [
                'file'     => '/repo/project/src/Controller/Bar.php',
                'line'     => 30,
                'class'    => 'App\\Controller\\Bar',
                'type'     => '->',
                'function' => 'handle',
            ],
        ];

        $normalized = $normalizer->normalizeFromFrames($frames);

        self::assertCount(2, $normalized);
        self::assertArrayHasKey('file', $normalized[0]);
        self::assertArrayHasKey('call', $normalized[0]);
        self::assertArrayHasKey('file', $normalized[1]);
        self::assertSame('src/Service/Foo.php', $normalized[0]['file'] ?? null);
        self::assertSame('App\\Service\\Foo->execute', $normalized[0]['call'] ?? null);
        self::assertSame('src/Controller/Bar.php', $normalized[1]['file'] ?? null);
    }

    public function testFallsBackToVendorFramesWhenNeeded(): void
    {
        $pathNormalizer = new PathNormalizer('/repo/project', true);
        $normalizer     = new TraceNormalizer($pathNormalizer, 3);

        $frames = [
            [
                'file'     => '/repo/project/vendor/codeception/file.php',
                'line'     => 10,
                'class'    => 'Vendor\\Runner',
                'type'     => '::',
                'function' => 'run',
            ],
        ];

        $normalized = $normalizer->normalizeFromFrames($frames, includeVendor: true);

        self::assertCount(1, $normalized);
        self::assertArrayHasKey('file', $normalized[0]);
        self::assertArrayHasKey('call', $normalized[0]);
        self::assertSame('vendor/codeception/file.php', $normalized[0]['file'] ?? null);
        self::assertSame('Vendor\\Runner::run', $normalized[0]['call'] ?? null);
    }
}
