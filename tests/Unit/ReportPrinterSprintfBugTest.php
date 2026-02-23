<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Exposes the upstream Codeception 5 ArgumentCountError in `Codeception\Reporter\ReportPrinter`
 * using the `--report` cli flag when a test name contains a `%`.
 *
 * @see https://github.com/Codeception/Codeception/pull/6927
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
            // This test is expected to fail with `ArgumentCountError` due to a bug in Codeception ReportPrinter
            '100% coverage' => [1],
        ];
    }
}
