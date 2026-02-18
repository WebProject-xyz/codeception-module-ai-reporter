<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Unit\Extension;

use Codeception\Event\PrintResultEvent;
use Codeception\ResultAggregator;
use Codeception\Test\Unit;
use function is_file;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use WebProject\Codeception\Module\AiReporter\Extension\AiReporter;

final class AiReporterTest extends Unit
{
    public function testAfterResultDoesNotThrowWhenReportWriteFails(): void
    {
        $outputFile = tempnam(sys_get_temp_dir(), 'ai-reporter-output-');
        self::assertIsString($outputFile);

        try {
            $reporter = new AiReporter(
                [
                    'format' => 'json',
                    'output' => $outputFile,
                ],
                [
                    'silent' => true,
                ]
            );

            $reporter->afterResult(new PrintResultEvent(new ResultAggregator()));
        } finally {
            if (is_file($outputFile)) {
                unlink($outputFile);
            }
        }
    }
}
