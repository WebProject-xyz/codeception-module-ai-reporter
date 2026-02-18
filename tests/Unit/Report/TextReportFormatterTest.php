<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Unit\Report;

use Codeception\Test\Unit;
use WebProject\Codeception\Module\AiReporter\Report\TextReportFormatter;

/**
 * @phpstan-import-type AiReport from \WebProject\Codeception\Module\AiReporter\Report\ReportTypes
 */
final class TextReportFormatterTest extends Unit
{
    public function testFormatsAllSectionsForSingleFailure(): void
    {
        $formatter = new TextReportFormatter();

        $report = [
            'run' => [
                'generated_at'     => '2026-02-18T23:59:59+00:00',
                'duration_seconds' => 0.123,
                'project_root'     => '/repo/project',
                'output_dir'       => '/repo/project/tests/_output',
            ],
            'summary' => [
                'tests'          => 1,
                'successful'     => 0,
                'failures'       => 1,
                'errors'         => 0,
                'warnings'       => 0,
                'skipped'        => 0,
                'incomplete'     => 0,
                'useless'        => 0,
                'assertions'     => 1,
                'successful_run' => false,
            ],
            'failures' => [
                [
                    'status' => 'failure',
                    'suite'  => 'Unit',
                    'test'   => [
                        'display_name' => 'Example test',
                        'file'         => 'tests/Unit/ExampleTest.php',
                        'signature'    => 'ExampleTest:testA',
                        'full_name'    => 'tests/Unit/ExampleTest.php:testA',
                    ],
                    'time_seconds' => 0.123,
                    'exception'    => [
                        'class'    => 'RuntimeException',
                        'message'  => 'Failed asserting that false is true',
                        'previous' => [],
                    ],
                    'scenario_steps' => [
                        ['step' => 'click "Login"', 'file' => 'tests/Acceptance/LoginCest.php', 'line' => 12, 'failed' => true],
                    ],
                    'trace' => [
                        ['file' => 'src/Service/Foo.php', 'line' => 90, 'call' => 'App\\Service\\Foo->run'],
                    ],
                    'artifacts' => [
                        'png' => 'tests/_output/fail.png',
                    ],
                    'hints' => [
                        'Assertion mismatch detected; compare expected and actual values at the top non-vendor frame.',
                    ],
                ],
            ],
        ];

        $output = $formatter->format($report);

        self::assertStringContainsString('Context', $output);
        self::assertStringContainsString('Failure 1', $output);
        self::assertStringContainsString('Exception', $output);
        self::assertStringContainsString('Scenario', $output);
        self::assertStringContainsString('Trace', $output);
        self::assertStringContainsString('Artifacts', $output);
        self::assertStringContainsString('Hints', $output);
        self::assertStringContainsString('Example test', $output);
    }

    public function testRendersComparisonFieldsWhenPresent(): void
    {
        $formatter = new TextReportFormatter();
        $report    = $this->makeReport([
            'comparison_expected' => "'hello'",
            'comparison_actual'   => "'world'",
            'comparison_diff'     => "--- Expected\n+++ Actual\n@@ @@\n-'hello'\n+'world'",
        ]);

        $output = $formatter->format($report);

        self::assertStringContainsString("comparison_expected: 'hello'", $output);
        self::assertStringContainsString("comparison_actual: 'world'", $output);
        self::assertStringContainsString('comparison_diff:', $output);
        self::assertStringContainsString('--- Expected', $output);
        self::assertStringContainsString("-'hello'", $output);
        self::assertStringContainsString("+'world'", $output);
    }

    public function testOmitsComparisonFieldsWhenAbsent(): void
    {
        $formatter = new TextReportFormatter();
        $report    = $this->makeReport([]);

        $output = $formatter->format($report);

        self::assertStringNotContainsString('comparison_expected', $output);
        self::assertStringNotContainsString('comparison_actual', $output);
        self::assertStringNotContainsString('comparison_diff', $output);
    }

    /**
     * @param array{comparison_expected?: string, comparison_actual?: string, comparison_diff?: string} $extraExceptionFields
     *
     * @return AiReport
     */
    private function makeReport(array $extraExceptionFields): array
    {
        $exception = [
            'class'    => 'PHPUnit\\Framework\\ExpectationFailedException',
            'message'  => 'Failed asserting that two strings are identical.',
            'previous' => [],
        ];

        if (isset($extraExceptionFields['comparison_expected'])) {
            $exception['comparison_expected'] = $extraExceptionFields['comparison_expected'];
        }
        if (isset($extraExceptionFields['comparison_actual'])) {
            $exception['comparison_actual'] = $extraExceptionFields['comparison_actual'];
        }
        if (isset($extraExceptionFields['comparison_diff'])) {
            $exception['comparison_diff'] = $extraExceptionFields['comparison_diff'];
        }

        return [
            'run' => [
                'generated_at'     => '2026-02-19T00:00:00+00:00',
                'duration_seconds' => 0.01,
                'project_root'     => '/repo/project',
                'output_dir'       => '/repo/project/tests/_output',
            ],
            'summary' => [
                'tests'          => 1,
                'successful'     => 0,
                'failures'       => 1,
                'errors'         => 0,
                'warnings'       => 0,
                'skipped'        => 0,
                'incomplete'     => 0,
                'useless'        => 0,
                'assertions'     => 1,
                'successful_run' => false,
            ],
            'failures' => [
                [
                    'status'         => 'failure',
                    'suite'          => 'Unit',
                    'test'           => [
                        'display_name' => 'example',
                        'signature'    => 'Example:test',
                        'full_name'    => 'tests/Unit/ExampleTest.php:test',
                        'file'         => 'tests/Unit/ExampleTest.php',
                    ],
                    'time_seconds'   => 0.001,
                    'exception'      => $exception,
                    'scenario_steps' => [],
                    'trace'          => [],
                    'artifacts'      => [],
                    'hints'          => [],
                ],
            ],
        ];
    }
}
