<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Extension;

use function array_slice;
use function array_values;
use Codeception\Event\FailEvent;
use Codeception\Event\PrintResultEvent;
use Codeception\Event\SuiteEvent;
use Codeception\Events;
use Codeception\Exception\ExtensionException;
use Codeception\Extension;
use Codeception\ResultAggregator;
use Codeception\Test\Descriptor;
use DateTimeImmutable;
use function explode;
use function in_array;
use InvalidArgumentException;
use function is_scalar;
use function json_encode;
use PHPUnit\Framework\ExpectationFailedException;
use function sprintf;
use Throwable;
use Webmozart\Assert\Assert;
use WebProject\Codeception\Module\AiReporter\Config\ReporterConfig;
use WebProject\Codeception\Module\AiReporter\Report\FilesystemWriter;
use WebProject\Codeception\Module\AiReporter\Report\HintGenerator;
use WebProject\Codeception\Module\AiReporter\Report\JsonReportFormatter;
use WebProject\Codeception\Module\AiReporter\Report\PathNormalizer;
use WebProject\Codeception\Module\AiReporter\Report\ScenarioExtractor;
use WebProject\Codeception\Module\AiReporter\Report\TextReportFormatter;
use WebProject\Codeception\Module\AiReporter\Report\TraceNormalizer;
use WebProject\Codeception\Module\AiReporter\Util\ConsoleText;
use WebProject\Codeception\Module\AiReporter\Util\TraceFrameProcessor;

/**
 * @phpstan-import-type AiReport from \WebProject\Codeception\Module\AiReporter\Report\ReportTypes
 * @phpstan-import-type Failure from \WebProject\Codeception\Module\AiReporter\Report\ReportTypes
 * @phpstan-import-type PreviousException from \WebProject\Codeception\Module\AiReporter\Report\ReportTypes
 * @phpstan-import-type RawConfig from \WebProject\Codeception\Module\AiReporter\Config\ReporterConfig
 * @phpstan-import-type SummaryInfo from \WebProject\Codeception\Module\AiReporter\Report\ReportTypes
 */
final class AiReporter extends Extension
{
    /**
     * @var array<string, string>
     */
    public static array $events = [
        Events::SUITE_BEFORE       => 'beforeSuite',
        Events::TEST_FAIL          => 'onFailure',
        Events::TEST_ERROR         => 'onError',
        Events::TEST_WARNING       => 'onWarning',
        Events::TEST_INCOMPLETE    => 'onIncomplete',
        Events::TEST_SKIPPED       => 'onSkipped',
        Events::TEST_USELESS       => 'onUseless',
        Events::RESULT_PRINT_AFTER => 'afterResult',
    ];

    /**
     * @var RawConfig
     */
    protected array $config = [
        'format'            => 'both',
        'output'            => '',
        'max_frames'        => 8,
        'include_steps'     => true,
        'include_artifacts' => true,
        'compact_paths'     => true,
    ];

    /** @var list<Failure> */
    private array $failures = [];

    private string $currentSuite = '';

    private float $startedAt;

    private ReporterConfig $runtimeConfig;

    private PathNormalizer $pathNormalizer;

    private TraceNormalizer $traceNormalizer;

    private ScenarioExtractor $scenarioExtractor;

    private TraceFrameProcessor $traceFrameProcessor;

    private HintGenerator $hintGenerator;

    private JsonReportFormatter $jsonFormatter;

    private TextReportFormatter $textFormatter;

    private FilesystemWriter $writer;

    private ConsoleText $consoleText;

    public function _initialize(): void
    {
        try {
            $logDir = $this->getLogDir();
            Assert::stringNotEmpty($logDir);

            $this->runtimeConfig = ReporterConfig::fromArray(
                $this->config,
                $logDir,
                $this->getRootDir(),
            );
        } catch (InvalidArgumentException $e) {
            throw new ExtensionException($this, $e->getMessage());
        }

        $this->pathNormalizer = new PathNormalizer(
            projectRoot: $this->getRootDir(),
            compactPaths: $this->runtimeConfig->compactPaths(),
        );
        $this->traceNormalizer     = new TraceNormalizer($this->pathNormalizer, $this->runtimeConfig->maxFrames());
        $this->scenarioExtractor   = new ScenarioExtractor($this->pathNormalizer);
        $this->traceFrameProcessor = new TraceFrameProcessor($this->pathNormalizer, $this->runtimeConfig->maxFrames());
        $this->hintGenerator       = new HintGenerator();
        $this->jsonFormatter       = new JsonReportFormatter();
        $this->textFormatter       = new TextReportFormatter();
        $this->writer              = new FilesystemWriter();
        $this->consoleText         = new ConsoleText();
        $this->startedAt           = microtime(true);
    }

    public function beforeSuite(SuiteEvent $event): void
    {
        $suite              = $event->getSuite();
        $this->currentSuite = null !== $suite ? $suite->getName() : '';
    }

    public function onFailure(FailEvent $event): void
    {
        $this->captureFailure('failure', $event);
    }

    public function onError(FailEvent $event): void
    {
        $this->captureFailure('error', $event);
    }

    public function onWarning(FailEvent $event): void
    {
        $this->captureFailure('warning', $event);
    }

    public function onIncomplete(FailEvent $event): void
    {
        $this->captureFailure('incomplete', $event);
    }

    public function onSkipped(FailEvent $event): void
    {
        $this->captureFailure('skipped', $event);
    }

    public function onUseless(FailEvent $event): void
    {
        $this->captureFailure('useless', $event);
    }

    public function afterResult(PrintResultEvent $event): void
    {
        $report    = $this->buildReport($event->getResult());
        $outputDir = $this->runtimeConfig->outputDir();

        if ($this->runtimeConfig->wantsJson()) {
            $jsonPath = $outputDir . '/ai-report.json';
            try {
                $this->writer->write($jsonPath, $this->jsonFormatter->format($report));
                $this->writeln(sprintf('- <bold>AI JSON</bold> report generated in <comment>file://%s</comment>', $jsonPath));
            } catch (Throwable $e) {
                $this->logReportWriteFailure('AI JSON', $e);
            }
        }

        if ($this->runtimeConfig->wantsText()) {
            $textPath = $outputDir . '/ai-report.txt';
            try {
                $this->writer->write($textPath, $this->textFormatter->format($report));
                $this->writeln(sprintf('- <bold>AI TEXT</bold> report generated in <comment>file://%s</comment>', $textPath));
            } catch (Throwable $e) {
                $this->logReportWriteFailure('AI TEXT', $e);
            }
        }
    }

    /** @param non-empty-string $status */
    private function captureFailure(string $status, FailEvent $event): void
    {
        $test      = $event->getTest();
        $throwable = $event->getFail();

        $trace         = $this->traceNormalizer->normalize($throwable);
        $trace         = $this->traceFrameProcessor->prepare($throwable, $trace);
        $scenarioSteps = $this->runtimeConfig->includeSteps()
            ? $this->scenarioExtractor->extract($test, $this->runtimeConfig->maxFrames())
            : [];

        $hints = array_values($this->hintGenerator->generate($throwable, $trace, $scenarioSteps));

        $exception = [
            'class'    => $throwable::class,
            'message'  => $throwable->getMessage(),
            'previous' => $this->extractPreviousExceptions($throwable),
        ];

        $comparison = $this->extractComparisonFailure($throwable);
        if (null !== $comparison) {
            $exception['comparison_expected'] = $comparison['comparison_expected'];
            $exception['comparison_actual']   = $comparison['comparison_actual'];
            $exception['comparison_diff']     = $comparison['comparison_diff'];
        }

        $failure = [
            'status' => $status,
            'suite'  => $this->currentSuite,
            'test'   => [
                'display_name' => Descriptor::getTestAsString($test),
                'signature'    => $test->getSignature(),
                'full_name'    => Descriptor::getTestFullName($test),
                'file'         => $this->pathNormalizer->normalize($test->getFileName()),
            ],
            'time_seconds'   => $event->getTime(),
            'exception'      => $exception,
            'scenario_steps' => $scenarioSteps,
            'trace'          => $trace,
            'artifacts'      => $this->runtimeConfig->includeArtifacts()
                ? $this->normalizeArtifacts($test->getMetadata()->getReports())
                : [],
            'hints' => $hints,
        ];

        /** @var Failure $failure */
        $this->failures[] = $failure;
        $this->printInlineContext($failure);
    }

    /** @param Failure $failure */
    private function printInlineContext(array $failure): void
    {
        if (!$this->shouldPrintInlineContext()) {
            return;
        }

        $status = $failure['status'];
        if (!in_array($status, ['failure', 'error', 'warning'], true)) {
            return;
        }

        $exception = $failure['exception'];
        $trace     = $failure['trace'];
        $hints     = $failure['hints'];
        $steps     = $failure['scenario_steps'];
        $artifacts = $failure['artifacts'];

        $this->writeln('  <comment>AI Context</comment>');
        $this->writeln(sprintf('    Exception: %s', $this->consoleText->escape($exception['class'])));
        $this->writeln(sprintf('    Message: %s', $this->consoleText->escape($this->consoleText->truncate($exception['message']))));

        if (isset($exception['comparison_diff']) && '' !== $exception['comparison_diff']) {
            $this->writeln('    Diff:');
            foreach (explode("\n", $exception['comparison_diff']) as $diffLine) {
                $this->writeln(sprintf('      %s', $this->consoleText->escape($diffLine)));
            }
        }

        if ([] !== $trace) {
            $this->writeln('    Trace:');
            foreach (array_slice($trace, 0, $this->runtimeConfig->maxFrames()) as $index => $frame) {
                $this->writeln(sprintf('      #%d %s', $index + 1, $this->consoleText->escape($this->traceFrameProcessor->formatFrame($frame))));
            }
        }

        if ([] !== $steps) {
            $this->writeln('    Scenario:');
            foreach (array_slice($steps, 0, 2) as $step) {
                $this->writeln(sprintf('      - %s', $this->consoleText->escape($step['step'])));
            }
        }

        if ([] !== $artifacts) {
            $this->writeln('    Artifacts:');
            foreach ($artifacts as $type => $path) {
                $this->writeln(sprintf('      - %s: %s', $this->consoleText->escape($type), $this->consoleText->escape($path)));
            }
        }

        if ([] !== $hints) {
            $this->writeln('    Hints:');
            foreach (array_slice($hints, 0, 3) as $hint) {
                $this->writeln(sprintf('      - %s', $this->consoleText->escape($hint)));
            }
        }
    }

    private function shouldPrintInlineContext(): bool
    {
        return (bool) ($this->options['report'] ?? false);
    }

    private function logReportWriteFailure(string $label, Throwable $exception): void
    {
        $this->writeln(
            sprintf(
                '- <error>%s report generation failed</error>: %s',
                $label,
                $this->consoleText->escape($this->consoleText->truncate($exception->getMessage()))
            )
        );
    }

    /** @return list<PreviousException> */
    private function extractPreviousExceptions(Throwable $throwable): array
    {
        $previous = [];
        $cursor   = $throwable->getPrevious();

        while (null !== $cursor) {
            $previous[] = [
                'class'   => $cursor::class,
                'message' => $cursor->getMessage(),
            ];
            $cursor = $cursor->getPrevious();
        }

        return $previous;
    }

    /** @return array{comparison_expected: string, comparison_actual: string, comparison_diff: string}|null */
    private function extractComparisonFailure(Throwable $throwable): ?array
    {
        if (!$throwable instanceof ExpectationFailedException) {
            return null;
        }

        $comparisonFailure = $throwable->getComparisonFailure();
        if (null === $comparisonFailure) {
            return null;
        }

        return [
            'comparison_expected' => $comparisonFailure->getExpectedAsString(),
            'comparison_actual'   => $comparisonFailure->getActualAsString(),
            'comparison_diff'     => $comparisonFailure->getDiff(),
        ];
    }

    /**
     * @param array<array-key, mixed> $reports
     *
     * @return array<string, string>
     */
    private function normalizeArtifacts(array $reports): array
    {
        $normalized = [];
        foreach ($reports as $type => $path) {
            if (is_scalar($path)) {
                $normalized[(string) $type] = $this->pathNormalizer->normalize((string) $path);
                continue;
            }

            $normalized[(string) $type] = (string) json_encode($path, JSON_INVALID_UTF8_SUBSTITUTE);
        }

        return $normalized;
    }

    /** @return AiReport */
    private function buildReport(ResultAggregator $result): array
    {
        /** @var SummaryInfo $summary */
        $summary = [
            'tests'          => (int) max(0, $result->testCount()),
            'successful'     => (int) max(0, $result->successfulCount()),
            'failures'       => (int) max(0, $result->failureCount()),
            'errors'         => (int) max(0, $result->errorCount()),
            'warnings'       => (int) max(0, $result->warningCount()),
            'skipped'        => (int) max(0, $result->skippedCount()),
            'incomplete'     => (int) max(0, $result->incompleteCount()),
            'useless'        => (int) max(0, $result->uselessCount()),
            'assertions'     => (int) max(0, $result->assertionCount()),
            'successful_run' => $result->wasSuccessful(),
        ];

        return [
            'run' => [
                'generated_at'     => (new DateTimeImmutable())->format(DATE_ATOM),
                'duration_seconds' => round(microtime(true) - $this->startedAt, 6),
                'project_root'     => rtrim(str_replace('\\', '/', $this->getRootDir()), '/'),
                'output_dir'       => $this->runtimeConfig->outputDir(),
            ],
            'summary'  => $summary,
            'failures' => $this->failures,
        ];
    }
}
