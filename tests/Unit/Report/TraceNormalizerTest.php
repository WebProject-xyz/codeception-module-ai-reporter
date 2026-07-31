<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Unit\Report;

use Codeception\Test\Unit;
use RuntimeException;
use WebProject\Codeception\Module\AiReporter\Report\TraceNormalizer;
use WebProject\Codeception\Module\AiReporter\Tests\Support\Fixture\PathNormalizerFactory;

final class TraceNormalizerTest extends Unit
{
    public function testFiltersVendorFramesByDefaultAndLimitsSize(): void
    {
        $normalizer = new TraceNormalizer(PathNormalizerFactory::make(), 2);

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
        $normalizer = new TraceNormalizer(PathNormalizerFactory::make(), 3);

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

    public function testRemoveNoiseFramesDropsFrameworkFramesWhenProjectFrameExists(): void
    {
        $normalizer = new TraceNormalizer(PathNormalizerFactory::make(), 8);

        $filtered = $normalizer->removeNoiseFrames([
            [
                'file' => 'vendor/phpunit/phpunit/src/Framework/Constraint/Constraint.php',
                'line' => 120,
                'call' => '[throw] PHPUnit\\Framework\\ExpectationFailedException',
            ],
            [
                'file' => 'tests/Unit/Report/FooTest.php',
                'line' => 55,
                'call' => 'PHPUnit\\Framework\\Assert::assertCount',
            ],
        ]);

        self::assertCount(1, $filtered);
        self::assertSame('tests/Unit/Report/FooTest.php', $filtered[0]['file'] ?? null);
    }

    public function testRemoveNoiseFramesFallsBackWhenOnlyFrameworkFramesExist(): void
    {
        $normalizer = new TraceNormalizer(PathNormalizerFactory::make(), 8);

        $filtered = $normalizer->removeNoiseFrames([
            [
                'file' => 'vendor/phpunit/phpunit/src/Framework/Constraint/Constraint.php',
                'line' => 120,
                'call' => '[throw] PHPUnit\\Framework\\ExpectationFailedException',
            ],
            [
                'file' => 'vendor/codeception/codeception/src/Codeception/Test/Test.php',
                'line' => 230,
                'call' => 'Codeception\\Test\\Test::dispatchOutcome',
            ],
        ]);

        self::assertCount(2, $filtered);
        self::assertSame('vendor/phpunit/phpunit/src/Framework/Constraint/Constraint.php', $filtered[0]['file'] ?? null);
    }

    public function testPrepareAddsOriginFrameAndFormatsFrame(): void
    {
        $normalizer = new TraceNormalizer(PathNormalizerFactory::make(), 2);

        $prepared = $normalizer->prepare(new RuntimeException('boom'), [
            [
                'file' => 'tests/Unit/Report/FooTest.php',
                'line' => 12,
                'call' => 'App\\Foo::bar',
            ],
        ]);

        self::assertCount(2, $prepared);
        self::assertStringStartsWith('[throw] RuntimeException', (string) ($prepared[0]['call'] ?? ''));
        self::assertSame('tests/Unit/Report/FooTest.php:12 App\\Foo::bar', $normalizer->formatFrame($prepared[1]));
    }
}
