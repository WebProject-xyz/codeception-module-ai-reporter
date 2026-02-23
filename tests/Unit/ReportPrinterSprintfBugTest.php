<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Validates that escaping a percent sign as `%%` works around the Codeception 5
 * upstream ArgumentCountError in `Codeception\Reporter\ReportPrinter` using the
 * `--report` cli flag.
 */
class ReportPrinterSprintfBugTest extends TestCase
{
    /**
     * @dataProvider provideEscapedPercentInNameCases
     */
    public function testEscapedPercentInName(int $value): void
    {
        self::assertSame(1, $value);
    }

    /**
     * @return array<string, array<int>>
     */
    public static function provideEscapedPercentInNameCases(): iterable
    {
        return [
            // The %% is rendered as % by the Codeception ReportPrinter sprintf
            '100%% safely escaped' => [1],
        ];
    }
}
