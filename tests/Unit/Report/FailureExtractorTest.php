<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Unit\Report;

use Codeception\Event\FailEvent;
use Codeception\Test\Unit;
use Exception;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use ReflectionClass;
use RuntimeException;
use SebastianBergmann\Comparator\ComparisonFailure;
use WebProject\Codeception\Module\AiReporter\Config\ReporterConfig;
use WebProject\Codeception\Module\AiReporter\Report\FailureExtractor;
use WebProject\Codeception\Module\AiReporter\Report\PathNormalizer;
use WebProject\Codeception\Module\AiReporter\Report\ScenarioExtractor;
use WebProject\Codeception\Module\AiReporter\Report\SourceExcerpt;
use WebProject\Codeception\Module\AiReporter\Report\TraceNormalizer;
use WebProject\Codeception\Module\AiReporter\Tests\Support\Fixture\StubTest;

/**
 * @phpstan-import-type RawConfig from ReporterConfig
 */
final class FailureExtractorTest extends Unit
{
    private string $projectRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectRoot = (string) realpath(__DIR__ . '/../../../../');
    }

    public function testExtractAssemblesFailureDataStructure(): void
    {
        $extractor = $this->makeExtractor();
        $test      = $this->makeStubTest('LoginCest:tryToLogIn');
        $event     = new FailEvent($test, new AssertionFailedError('expected truthy'), 0.1234567);

        $failure = $extractor->extract('failure', $event, 'acceptance');

        self::assertSame('failure', $failure['status']);
        self::assertSame('acceptance', $failure['suite']);
        self::assertSame('LoginCest:tryToLogIn', $failure['test']['signature']);
        self::assertSame('vendor/bin/codecept run tests/Support/Fixture/StubTest.php:tryToLogIn', $failure['rerun']);
        self::assertSame(0.123457, $failure['time_seconds']);
        self::assertSame('PHPUnit\Framework\AssertionFailedError', $failure['exception']['class']);
        self::assertSame('expected truthy', $failure['exception']['message']);
        self::assertSame([], $failure['exception']['previous']);
        self::assertNotEmpty($failure['trace']);
    }

    public function testExtractCapturesPreviousExceptionCausalityChain(): void
    {
        $extractor = $this->makeExtractor();
        $test      = $this->makeStubTest('DbCest:query');

        $root = new RuntimeException('Connection timed out');
        $mid  = new Exception('Query failed', 0, $root);
        $top  = new RuntimeException('Transaction aborted', 0, $mid);

        $event   = new FailEvent($test, $top, 0.01);
        $failure = $extractor->extract('error', $event, 'unit');

        self::assertSame('RuntimeException', $failure['exception']['class']);
        self::assertSame('Transaction aborted', $failure['exception']['message']);

        $previous = $failure['exception']['previous'];
        self::assertCount(2, $previous);
        self::assertSame('Exception', $previous[0]['class']);
        self::assertSame('Query failed', $previous[0]['message']);
        self::assertSame('RuntimeException', $previous[1]['class']);
        self::assertSame('Connection timed out', $previous[1]['message']);
    }

    public function testExtractCapturesComparisonFailureDetails(): void
    {
        $extractor = $this->makeExtractor();
        $test      = $this->makeStubTest('CompareCest:check');

        $throwable = new ExpectationFailedException(
            'Failed asserting that two strings are identical.',
            new ComparisonFailure('expected_val', 'actual_val', "'expected_val'", "'actual_val'"),
        );

        $event   = new FailEvent($test, $throwable, 0.02);
        $failure = $extractor->extract('failure', $event, 'unit');

        self::assertSame("'expected_val'", $failure['exception']['comparison_expected'] ?? null);
        self::assertSame("'actual_val'", $failure['exception']['comparison_actual'] ?? null);
        self::assertStringStartsWith('--- Expected', (string) ($failure['exception']['comparison_diff'] ?? ''));
    }

    public function testExtractNormalizesTestArtifactsWhenEnabled(): void
    {
        $extractor = $this->makeExtractor(['include_artifacts' => true]);
        $test      = $this->makeStubTest('UiCest:screenshot');
        $test->getMetadata()->addReport('png', $this->projectRoot . '/tests/_output/fail.png');
        $test->getMetadata()->addReport('data', ['key' => 'val']);

        $event   = new FailEvent($test, new RuntimeException('boom'), 0.01);
        $failure = $extractor->extract('failure', $event, 'functional');

        self::assertSame('tests/_output/fail.png', $failure['artifacts']['png']);
        self::assertSame('{"key":"val"}', $failure['artifacts']['data']);
    }

    public function testExtractOmitsArtifactsWhenDisabled(): void
    {
        $extractor = $this->makeExtractor(['include_artifacts' => false]);
        $test      = $this->makeStubTest('UiCest:screenshot');
        $test->getMetadata()->addReport('png', $this->projectRoot . '/tests/_output/fail.png');

        $event   = new FailEvent($test, new RuntimeException('boom'), 0.01);
        $failure = $extractor->extract('failure', $event, 'functional');

        self::assertSame([], $failure['artifacts']);
    }

    /**
     * @param RawConfig $rawConfig
     */
    private function makeExtractor(array $rawConfig = []): FailureExtractor
    {
        $config = ReporterConfig::fromArray(
            $rawConfig,
            $this->projectRoot . '/tests/_output',
            $this->projectRoot,
        );

        $pathNormalizer    = new PathNormalizer($this->projectRoot, $config->compactPaths());
        $traceNormalizer   = new TraceNormalizer($pathNormalizer, $config->maxFrames());
        $scenarioExtractor = new ScenarioExtractor($pathNormalizer);
        $sourceExcerpt     = new SourceExcerpt($pathNormalizer, $this->projectRoot, $config->contextLines());

        return new FailureExtractor(
            $config,
            $pathNormalizer,
            $traceNormalizer,
            $scenarioExtractor,
            $sourceExcerpt,
        );
    }

    private function makeStubTest(string $signature): StubTest
    {
        $filename = (new ReflectionClass(StubTest::class))->getFileName();
        self::assertNotFalse($filename);

        return new StubTest('stub', $filename, $signature);
    }
}
