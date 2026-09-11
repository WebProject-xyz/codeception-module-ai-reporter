<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Report;

use Codeception\Event\FailEvent;
use Codeception\Test\Descriptor;
use PHPUnit\Framework\ExpectationFailedException;
use Throwable;
use WebProject\Codeception\Module\AiReporter\Config\ReporterConfig;

use function is_scalar;
use function json_encode;
use function round;
use function trim;

use const JSON_INVALID_UTF8_SUBSTITUTE;

/**
 * @phpstan-import-type Failure from ReportTypes
 * @phpstan-import-type PreviousException from ReportTypes
 */
final class FailureExtractor
{
    public function __construct(
        private readonly ReporterConfig $config,
        private readonly PathNormalizer $pathNormalizer,
        private readonly TraceNormalizer $traceNormalizer,
        private readonly ScenarioExtractor $scenarioExtractor,
        private readonly SourceExcerpt $sourceExcerpt,
    ) {
    }

    /**
     * @param non-empty-string $status
     *
     * @return Failure
     */
    public function extract(string $status, FailEvent $event, string $suite): array
    {
        $test      = $event->getTest();
        $throwable = $event->getFail();

        $trace         = $this->traceNormalizer->normalize($throwable);
        $scenarioSteps = $this->config->includeSteps()
            ? $this->scenarioExtractor->extract($test, $this->config->maxFrames())
            : [];

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

        $fullName = Descriptor::getTestFullName($test);

        /** @var Failure $failure */
        $failure = [
            'status' => $status,
            'suite'  => $suite,
            'test'   => [
                'display_name' => Descriptor::getTestAsString($test),
                'signature'    => $test->getSignature(),
                'full_name'    => $fullName,
                'file'         => $this->pathNormalizer->normalize($test->getFileName()),
            ],
            'rerun'          => 'vendor/bin/codecept run ' . $fullName,
            'time_seconds'   => round($event->getTime(), 6),
            'exception'      => $exception,
            'scenario_steps' => $scenarioSteps,
            'trace'          => $trace,
            'source_context' => $this->sourceExcerpt->forTrace($trace),
            'artifacts'      => $this->config->includeArtifacts()
                ? $this->normalizeArtifacts($test->getMetadata()->getReports())
                : [],
        ];

        return $failure;
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
            'comparison_diff'     => trim($comparisonFailure->getDiff()),
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
            $normalized[(string) $type] = is_scalar($path)
                ? (string) $this->pathNormalizer->normalize((string) $path)
                : (string) json_encode($path, JSON_INVALID_UTF8_SUBSTITUTE);
        }

        return $normalized;
    }
}
