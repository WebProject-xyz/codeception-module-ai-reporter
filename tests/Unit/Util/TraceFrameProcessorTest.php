<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Unit\Util;

use Codeception\Test\Unit;
use RuntimeException;
use WebProject\Codeception\Module\AiReporter\Report\PathNormalizer;
use WebProject\Codeception\Module\AiReporter\Util\TraceFrameProcessor;

final class TraceFrameProcessorTest extends Unit
{
    public function testRemoveNoiseFramesDropsFrameworkFramesWhenProjectFrameExists(): void
    {
        $processor = new TraceFrameProcessor(new PathNormalizer('/repo/project', true), 8);

        $trace = [
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
        ];

        $filtered = $processor->removeNoiseFrames($trace);

        self::assertCount(1, $filtered);
        self::assertSame('tests/Unit/Report/FooTest.php', $filtered[0]['file'] ?? null);
    }

    public function testRemoveNoiseFramesFallsBackWhenOnlyFrameworkFramesExist(): void
    {
        $processor = new TraceFrameProcessor(new PathNormalizer('/repo/project', true), 8);

        $trace = [
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
        ];

        $filtered = $processor->removeNoiseFrames($trace);

        self::assertCount(2, $filtered);
        self::assertSame('vendor/phpunit/phpunit/src/Framework/Constraint/Constraint.php', $filtered[0]['file'] ?? null);
    }

    public function testPrepareAddsOriginFrameAndFormatsFrame(): void
    {
        $processor = new TraceFrameProcessor(new PathNormalizer('/repo/project', true), 2);

        $throwable = new RuntimeException('boom');
        $prepared  = $processor->prepare($throwable, [
            [
                'file' => 'tests/Unit/Report/FooTest.php',
                'line' => 12,
                'call' => 'App\\Foo::bar',
            ],
        ]);

        self::assertNotEmpty($prepared);
        self::assertStringStartsWith('[throw] RuntimeException', (string) ($prepared[0]['call'] ?? ''));
        self::assertCount(2, $prepared);

        $formatted = $processor->formatFrame($prepared[1]);
        self::assertSame('tests/Unit/Report/FooTest.php:12 App\\Foo::bar', $formatted);
    }
}
