<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Report;

/**
 * @phpstan-type TraceFrame array{file?: string, line?: int, call?: string}
 * @phpstan-type ScenarioStep array{step: string, file?: string, line?: int, failed: bool}
 * @phpstan-type PreviousException array{class: non-empty-string, message: string}
 * @phpstan-type ExceptionInfo array{class: non-empty-string, message: string, previous: array<int, PreviousException>, comparison_expected?: string, comparison_actual?: string, comparison_diff?: string}
 * @phpstan-type TestInfo array{display_name: string, signature: string, full_name: string, file: string|null}
 * @phpstan-type Failure array{
 *     status: non-empty-string,
 *     suite: string,
 *     test: TestInfo,
 *     time_seconds: float,
 *     exception: ExceptionInfo,
 *     scenario_steps: array<int, ScenarioStep>,
 *     trace: array<int, TraceFrame>,
 *     artifacts: array<string, string>,
 *     hints: array<int, string>
 * }
 * @phpstan-type RunInfo array{
 *     generated_at: string,
 *     duration_seconds: float,
 *     project_root: string,
 *     output_dir: string
 * }
 * @phpstan-type SummaryInfo array{
 *     tests: int<0, max>,
 *     successful: int<0, max>,
 *     failures: int<0, max>,
 *     errors: int<0, max>,
 *     warnings: int<0, max>,
 *     skipped: int<0, max>,
 *     incomplete: int<0, max>,
 *     useless: int<0, max>,
 *     assertions: int<0, max>,
 *     successful_run: bool
 * }
 * @phpstan-type AiReport array{
 *     run: RunInfo,
 *     summary: SummaryInfo,
 *     failures: array<int, Failure>
 * }
 */
final class ReportTypes
{
}
