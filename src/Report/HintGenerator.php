<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Report;

use function array_values;
use function str_contains;
use Throwable;

/**
 * @phpstan-import-type TraceFrame from ReportTypes
 * @phpstan-import-type ScenarioStep from ReportTypes
 */
final class HintGenerator
{
    /**
     * @param array<int, TraceFrame>   $trace
     * @param array<int, ScenarioStep> $steps
     *
     * @return string[]
     */
    public function generate(Throwable $throwable, array $trace, array $steps): array
    {
        $hints = [];

        $class   = $throwable::class;
        $message = $throwable->getMessage();

        if (str_contains($class, 'Assertion') || str_contains($message, 'Failed asserting')) {
            $hints['assertion'] = 'Assertion mismatch detected; compare expected and actual values at the top non-vendor frame.';
        }

        if (str_contains($class, 'TypeError') || str_contains($class, 'ArgumentCountError') || str_contains($class, 'Error')) {
            $hints['runtime'] = 'Runtime/type issue detected; verify argument types and nullability at the top frame.';
        }

        if (str_contains($message, 'NoSuchElement') || str_contains($message, 'element')) {
            $hints['ui'] = 'UI element lookup failed; verify locator stability and page state before this step.';
        }

        if ([] === $trace) {
            $hints['trace'] = 'No filtered trace frames were captured; rerun with higher verbosity if deeper diagnostics are needed.';
        }

        if ([] !== $steps) {
            $hints['steps'] = 'Use the last scenario step to reproduce the failure quickly before expanding diagnostics.';
        }

        if ([] === $hints) {
            $hints['generic'] = 'Start with the first trace frame and exception message, then reproduce with only the failing test.';
        }

        return array_values($hints);
    }
}
