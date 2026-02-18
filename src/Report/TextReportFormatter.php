<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Report;

use function implode;
use function rtrim;
use function sprintf;

/**
 * @phpstan-import-type AiReport from \WebProject\Codeception\Module\AiReporter\Report\ReportTypes
 */
final class TextReportFormatter
{
    /** @param AiReport $report */
    public function format(array $report): string
    {
        $lines = [];

        $run      = $report['run'];
        $summary  = $report['summary'];
        $failures = $report['failures'];

        $lines[] = 'Context';
        $lines[] = sprintf('generated_at: %s', $run['generated_at']);
        $lines[] = sprintf('project_root: %s', $run['project_root']);
        $lines[] = sprintf(
            'totals: tests=%d successful=%d failures=%d errors=%d warnings=%d skipped=%d incomplete=%d useless=%d assertions=%d',
            $summary['tests'],
            $summary['successful'],
            $summary['failures'],
            $summary['errors'],
            $summary['warnings'],
            $summary['skipped'],
            $summary['incomplete'],
            $summary['useless'],
            $summary['assertions'],
        );
        $lines[] = '';

        if ([] === $failures) {
            $lines[] = 'Failure';
            $lines[] = 'none';
            $lines[] = '';

            return implode("\n", $lines);
        }

        foreach ($failures as $index => $failure) {
            $number  = $index + 1;
            $lines[] = sprintf('Failure %d', $number);
            $lines[] = sprintf('status: %s', $failure['status']);
            $lines[] = sprintf('suite: %s', $failure['suite']);
            $lines[] = sprintf('test: %s', $failure['test']['display_name']);
            $lines[] = sprintf('test_file: %s', $failure['test']['file'] ?? '');
            $lines[] = sprintf('test_signature: %s', $failure['test']['signature']);
            $lines[] = sprintf('duration_seconds: %s', (string) $failure['time_seconds']);
            $lines[] = '';

            $lines[] = 'Exception';
            $lines[] = sprintf('exception_class: %s', $failure['exception']['class']);
            $lines[] = sprintf('message: %s', $failure['exception']['message']);

            $exceptionData = $failure['exception'];
            if (isset($exceptionData['comparison_expected']) && '' !== $exceptionData['comparison_expected']) {
                $lines[] = sprintf('comparison_expected: %s', $exceptionData['comparison_expected']);
            }
            if (isset($exceptionData['comparison_actual']) && '' !== $exceptionData['comparison_actual']) {
                $lines[] = sprintf('comparison_actual: %s', $exceptionData['comparison_actual']);
            }
            if (isset($exceptionData['comparison_diff']) && '' !== $exceptionData['comparison_diff']) {
                $lines[] = 'comparison_diff:';
                $lines[] = rtrim($exceptionData['comparison_diff']);
            }

            $chain = $failure['exception']['previous'];
            if ([] !== $chain) {
                $lines[] = 'previous_exceptions:';
                foreach ($chain as $previous) {
                    $lines[] = sprintf('- %s: %s', $previous['class'], $previous['message']);
                }
            }
            $lines[] = '';

            $lines[] = 'Scenario';
            $steps   = $failure['scenario_steps'];
            if ([] === $steps) {
                $lines[] = 'none';
            } else {
                foreach ($steps as $step) {
                    $failed   = $step['failed'] ? ' [FAILED]' : '';
                    $location = '';
                    if (isset($step['file'], $step['line'])) {
                        $location = sprintf(' (%s:%d)', $step['file'], $step['line']);
                    }
                    $lines[] = sprintf('- %s%s%s', $step['step'], $location, $failed);
                }
            }
            $lines[] = '';

            $lines[] = 'Trace';
            $trace   = $failure['trace'];
            if ([] === $trace) {
                $lines[] = 'none';
            } else {
                foreach ($trace as $traceIndex => $frame) {
                    $location = '';
                    if (isset($frame['file'], $frame['line'])) {
                        $location = sprintf('%s:%d', $frame['file'], $frame['line']);
                    } elseif (isset($frame['file'])) {
                        $location = (string) $frame['file'];
                    }
                    $call    = $frame['call'] ?? '';
                    $lines[] = sprintf('#%d %s %s', $traceIndex + 1, $location, $call);
                }
            }
            $lines[] = '';

            $lines[]   = 'Artifacts';
            $artifacts = $failure['artifacts'];
            if ([] === $artifacts) {
                $lines[] = 'none';
            } else {
                foreach ($artifacts as $type => $artifactPath) {
                    $lines[] = sprintf('- %s: %s', $type, (string) $artifactPath);
                }
            }
            $lines[] = '';

            $lines[] = 'Hints';
            $hints   = $failure['hints'];
            if ([] === $hints) {
                $lines[] = '- none';
            } else {
                foreach ($hints as $hint) {
                    $lines[] = sprintf('- %s', $hint);
                }
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
