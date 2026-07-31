<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Unit\Extension;

use function chr;
use Codeception\Event\FailEvent;
use Codeception\Event\PrintResultEvent;
use Codeception\ResultAggregator;
use Codeception\Test\Unit;
use function file_get_contents;
use function is_dir;
use function is_file;
use function json_decode;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use ReflectionClass;
use ReflectionProperty;
use function rmdir;
use RuntimeException;
use SebastianBergmann\Comparator\ComparisonFailure;
use function sys_get_temp_dir;
use function tempnam;
use Throwable;
use function uniqid;
use function unlink;
use WebProject\Codeception\Module\AiReporter\Extension\AiReporter;
use WebProject\Codeception\Module\AiReporter\Tests\Support\Fixture\CapturingOutput;
use WebProject\Codeception\Module\AiReporter\Tests\Support\Fixture\StubTest;

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

    public function testInlineContextPrintsTestFailedLineForFailure(): void
    {
        $reporter = $this->makeReporter(['report' => true]);
        $output   = $this->captureOutput($reporter);

        $reporter->onFailure(new FailEvent(
            $this->makeStubTest('LoginCest:tryToLogIn'),
            new AssertionFailedError('expected truthy'),
            0.01,
        ));

        $text = $output->fetch();
        self::assertStringContainsString('AI Context', $text);
        self::assertStringContainsString('Test failed:', $text);
        self::assertStringContainsString(':tryToLogIn', $text);
        self::assertStringContainsString('Exception: PHPUnit\\Framework\\AssertionFailedError', $text);
        self::assertStringContainsString('Message: expected truthy', $text);
    }

    public function testInlineContextPrintsTestFailedLineForError(): void
    {
        $reporter = $this->makeReporter(['report' => true]);
        $output   = $this->captureOutput($reporter);

        $reporter->onError(new FailEvent(
            $this->makeStubTest('ErrorCest:explodes'),
            new RuntimeException('kaboom'),
            0.0,
        ));

        $text = $output->fetch();
        self::assertStringContainsString('Test failed:', $text);
        self::assertStringContainsString(':explodes', $text);
        self::assertStringContainsString('Message: kaboom', $text);
    }

    public function testInlineContextPrintsTestFailedLineForWarning(): void
    {
        $reporter = $this->makeReporter(['report' => true]);
        $output   = $this->captureOutput($reporter);

        $reporter->onWarning(new FailEvent(
            $this->makeStubTest('WarnCest:complains'),
            new RuntimeException('be careful'),
            0.0,
        ));

        $text = $output->fetch();
        self::assertStringContainsString('Test failed:', $text);
        self::assertStringContainsString(':complains', $text);
    }

    public function testInlineContextNotPrintedWhenReportOptionDisabled(): void
    {
        $reporter = $this->makeReporter([]);
        $output   = $this->captureOutput($reporter);

        $reporter->onFailure(new FailEvent(
            $this->makeStubTest('a:b'),
            new RuntimeException('boom'),
            0.0,
        ));

        self::assertSame('', $output->fetch());
    }

    public function testInlineContextNotPrintedForNonFailureStatuses(): void
    {
        $reporter = $this->makeReporter(['report' => true]);
        $output   = $this->captureOutput($reporter);

        $event = new FailEvent(
            $this->makeStubTest('a:b'),
            new RuntimeException('skip me'),
            0.0,
        );

        $reporter->onSkipped($event);
        $reporter->onIncomplete($event);
        $reporter->onUseless($event);

        self::assertSame('', $output->fetch());
    }

    public function testAfterResultWritesJsonAndTextReportsCapturedFromFailure(): void
    {
        $reports = $this->captureReports(new AssertionFailedError('expected truthy'));

        self::assertStringContainsString('"full_name"', $reports['json']);
        self::assertStringContainsString('PHPUnit\\\\Framework\\\\AssertionFailedError', $reports['json']);
        self::assertStringContainsString('expected truthy', $reports['json']);
        self::assertStringContainsString('expected truthy', $reports['text']);
    }

    public function testJsonReportSubstitutesInvalidUtf8InsteadOfFailing(): void
    {
        $reports = $this->captureReports(new RuntimeException('bad:' . chr(177)));

        self::assertStringContainsString('bad:', $reports['json']);
        self::assertStringContainsString('"failures"', $reports['json']);
    }

    public function testJsonReportIncludesComparisonFieldsWhenPresent(): void
    {
        $reports = $this->captureReports(new ExpectationFailedException(
            'Failed asserting that two strings are identical.',
            new ComparisonFailure('hello', 'world', "'hello'", "'world'"),
        ));

        $decoded = json_decode($reports['json'], true);
        self::assertIsArray($decoded);

        /** @var array{failures: list<array{exception: array{comparison_expected: string, comparison_actual: string, comparison_diff: string}}>} $decoded */
        $exception = $decoded['failures'][0]['exception'];

        self::assertSame("'hello'", $exception['comparison_expected']);
        self::assertSame("'world'", $exception['comparison_actual']);
        self::assertStringContainsString('--- Expected', $exception['comparison_diff']);
    }

    /**
     * @return array{json: string, text: string}
     */
    private function captureReports(Throwable $fail): array
    {
        $outputDir = sys_get_temp_dir() . '/' . uniqid('ai-reporter-out-', true);

        $reporter = new AiReporter(
            [
                'format' => 'both',
                'output' => $outputDir,
            ],
            [],
        );

        try {
            $reporter->onFailure(new FailEvent($this->makeStubTest('LoginCest:tryToLogIn'), $fail, 0.01));
            $reporter->afterResult(new PrintResultEvent(new ResultAggregator()));

            $jsonPath = $outputDir . '/ai-report.json';
            $textPath = $outputDir . '/ai-report.txt';

            self::assertFileExists($jsonPath);
            self::assertFileExists($textPath);

            return [
                'json' => (string) file_get_contents($jsonPath),
                'text' => (string) file_get_contents($textPath),
            ];
        } finally {
            foreach (['ai-report.json', 'ai-report.txt'] as $name) {
                $path = $outputDir . '/' . $name;
                if (is_file($path)) {
                    unlink($path);
                }
            }
            if (is_dir($outputDir)) {
                rmdir($outputDir);
            }
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function makeReporter(array $options): AiReporter
    {
        return new AiReporter(
            [
                'format' => 'both',
                'output' => sys_get_temp_dir(),
            ],
            $options,
        );
    }

    private function captureOutput(AiReporter $reporter): CapturingOutput
    {
        $buffer = new CapturingOutput();
        $prop   = new ReflectionProperty($reporter, 'output');
        $prop->setValue($reporter, $buffer);

        return $buffer;
    }

    private function makeStubTest(string $signature): StubTest
    {
        $filename = (new ReflectionClass(StubTest::class))->getFileName();
        self::assertNotFalse($filename);

        return new StubTest('stub', $filename, $signature);
    }
}
