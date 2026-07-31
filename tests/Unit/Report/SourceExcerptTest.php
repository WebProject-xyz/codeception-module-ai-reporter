<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Unit\Report;

use Codeception\Test\Unit;
use function dirname;
use WebProject\Codeception\Module\AiReporter\Report\SourceExcerpt;

final class SourceExcerptTest extends Unit
{
    public function testReadsCodeAroundFirstProjectFrame(): void
    {
        $excerpt = new SourceExcerpt($this->projectRoot(), 2);

        $context = $excerpt->forTrace([
            ['file' => 'vendor/phpunit/phpunit/src/Framework/Assert.php', 'line' => 10],
            ['file' => 'tests/Support/Fixture/PathNormalizerFactory.php', 'line' => 13],
        ]);

        self::assertNotNull($context);
        self::assertSame('tests/Support/Fixture/PathNormalizerFactory.php', $context['file']);
        self::assertSame(13, $context['line']);
        self::assertSame(11, $context['start_line']);
        self::assertCount(5, $context['lines']);
        self::assertStringContainsString('PathNormalizer', $context['lines'][2]);
    }

    public function testKeepsMoreLinesBeforeTheFrameThanAfter(): void
    {
        $excerpt = new SourceExcerpt($this->projectRoot(), 6);

        $context = $excerpt->forTrace([['file' => 'src/Extension/AiReporter.php', 'line' => 100]]);

        self::assertNotNull($context);
        self::assertSame(94, $context['start_line']);
        self::assertCount(9, $context['lines']);
    }

    public function testClampsWindowAtStartOfFile(): void
    {
        $excerpt = new SourceExcerpt($this->projectRoot(), 4);

        $context = $excerpt->forTrace([['file' => 'tests/Support/Fixture/PathNormalizerFactory.php', 'line' => 2]]);

        self::assertNotNull($context);
        self::assertSame(1, $context['start_line']);
        self::assertSame('<?php', $context['lines'][0]);
    }

    public function testReturnsNullWhenNoReadableProjectFrameExists(): void
    {
        $excerpt = new SourceExcerpt($this->projectRoot(), 2);

        self::assertNull($excerpt->forTrace([]));
        self::assertNull($excerpt->forTrace([['file' => 'vendor/phpunit/phpunit/src/Framework/Assert.php', 'line' => 10]]));
        self::assertNull($excerpt->forTrace([['file' => 'tests/Unit/DoesNotExist.php', 'line' => 3]]));
        self::assertNull($excerpt->forTrace([['file' => 'tests/Support/Fixture/PathNormalizerFactory.php', 'line' => 9999]]));
        self::assertNull($excerpt->forTrace([['call' => 'Closure->__invoke']]));
    }

    public function testDisabledWhenContextLinesIsZero(): void
    {
        $excerpt = new SourceExcerpt($this->projectRoot(), 0);

        self::assertNull($excerpt->forTrace([['file' => 'tests/Support/Fixture/PathNormalizerFactory.php', 'line' => 13]]));
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }
}
