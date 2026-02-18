<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Report;

use JsonException;

/**
 * @phpstan-import-type AiReport from \WebProject\Codeception\Module\AiReporter\Report\ReportTypes
 */
final class JsonReportFormatter
{
    /** @param AiReport $report
     * @throws JsonException
     */
    public function format(array $report): string
    {
        return json_encode(
            $report,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        ) . "\n";
    }
}
