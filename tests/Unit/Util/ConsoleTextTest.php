<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Unit\Util;

use Codeception\Test\Unit;
use WebProject\Codeception\Module\AiReporter\Util\ConsoleText;

final class ConsoleTextTest extends Unit
{
    public function testEscapeNormalizesNewlinesAndEscapesConsoleTags(): void
    {
        $util = new ConsoleText();

        $escaped = $util->escape("<error>bad\nline\r</error>");

        self::assertStringNotContainsString("\n", $escaped);
        self::assertStringNotContainsString("\r", $escaped);
        self::assertStringContainsString('\\n', $escaped);
        self::assertStringContainsString('\\r', $escaped);
        self::assertStringContainsString('\\<error\\>', $escaped);
    }

    public function testTruncateAddsEllipsisWhenNeeded(): void
    {
        $util = new ConsoleText();

        self::assertSame('short', $util->truncate('short', 10));
        self::assertSame('ab...', $util->truncate('abcdef', 5));
    }
}
