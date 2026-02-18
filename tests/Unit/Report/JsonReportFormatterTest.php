<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Unit\Report;

use function chr;
use Codeception\Test\Unit;
use function json_decode;
use WebProject\Codeception\Module\AiReporter\Report\JsonReportFormatter;

final class JsonReportFormatterTest extends Unit
{
    public function testFormatsReportWithInvalidUtf8UsingReplacementCharacter(): void
    {
        $formatter = new JsonReportFormatter();

        $json = $formatter->format([
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
                    'status'       => 'failure',
                    'suite'        => 'Unit',
                    'test'         => [
                        'display_name' => 'example',
                        'signature'    => 'Example:test',
                        'full_name'    => 'tests/Unit/ExampleTest.php:test',
                        'file'         => 'tests/Unit/ExampleTest.php',
                    ],
                    'time_seconds' => 0.001,
                    'exception'    => [
                        'class'    => 'RuntimeException',
                        'message'  => 'bad:' . chr(177),
                        'previous' => [],
                    ],
                    'scenario_steps' => [],
                    'trace'          => [],
                    'artifacts'      => [],
                    'hints'          => [],
                ],
            ],
        ]);

        self::assertStringContainsString('"failures"', $json);
        self::assertStringContainsString('bad:', $json);
    }

    public function testIncludesComparisonFieldsInJsonWhenPresent(): void
    {
        $formatter = new JsonReportFormatter();

        $json = $formatter->format([
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
                    'status'       => 'failure',
                    'suite'        => 'Unit',
                    'test'         => [
                        'display_name' => 'example',
                        'signature'    => 'Example:test',
                        'full_name'    => 'tests/Unit/ExampleTest.php:test',
                        'file'         => 'tests/Unit/ExampleTest.php',
                    ],
                    'time_seconds' => 0.001,
                    'exception'    => [
                        'class'               => 'PHPUnit\\Framework\\ExpectationFailedException',
                        'message'             => 'Failed asserting that two strings are identical.',
                        'previous'            => [],
                        'comparison_expected' => "'hello'",
                        'comparison_actual'   => "'world'",
                        'comparison_diff'     => "--- Expected\n+++ Actual\n@@ @@\n-'hello'\n+'world'",
                    ],
                    'scenario_steps' => [],
                    'trace'          => [],
                    'artifacts'      => [],
                    'hints'          => [],
                ],
            ],
        ]);

        $decoded = json_decode($json, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('failures', $decoded);

        /** @var list<array{exception: array{comparison_expected: string, comparison_actual: string, comparison_diff: string}}> $failures */
        $failures  = $decoded['failures'];
        $exception = $failures[0]['exception'];

        self::assertSame("'hello'", $exception['comparison_expected']);
        self::assertSame("'world'", $exception['comparison_actual']);
        self::assertStringContainsString('--- Expected', $exception['comparison_diff']);
    }
}
