# Domain Context: AI Codeception Reporter

This document defines the ubiquitous domain concepts and terminology for `webproject-xyz/ai-codeception-reporter`.

## Core Concepts

### Run
A single test execution session within Codeception. Captures metadata about the environment, including `generated_at` timestamp, `duration_seconds`, `project_root`, and `output_dir`.

### Summary
The aggregate numerical result of a run, tallying `tests`, `successful`, `failures`, `errors`, `warnings`, `skipped`, `incomplete`, `useless`, and `assertions`, along with a boolean `successful_run` flag.

### Failure
A structured representation of an unsuccessful test event (`failure`, `error`, `warning`, `incomplete`, `skipped`, or `useless`). A failure record captures:
- **Test metadata**: display name, full signature, full name, file path.
- **Rerun command**: deterministic single-test CLI rerun invocation (`vendor/bin/codecept run ...`).
- **Exception details**: class name, message, comparison failure diff (expected vs actual), and cause chain (`previous` exceptions).
- **Trace**: normalized, noise-filtered stack frames.
- **Source context**: an excerpt of source code around the first userland frame.
- **Scenario steps**: executed steps in scenario-driven tests with failure annotations.
- **Artifacts**: map of generated file artifacts (e.g. screenshots, HTML dumps) produced by the test.

### AiReport
The top-level report structure combining `run` metadata, the outcome `summary`, and the list of `failures`. Emitted deterministically to `ai-report.json` and optionally `ai-report.txt`.

## Modules & Processing Pipeline

### FailureExtractor
The primary deep module responsible for transforming Codeception failure events (`FailEvent`) and associated throwables into fully resolved, validated `Failure` data models. It encapsulates test inspection, causality tracking, comparison parsing, and artifact resolution.

### TraceNormalizer
Responsible for normalizing raw stack traces into sanitized `TraceFrame` records: prepends the synthetic throw-site origin frame, removes framework/runner noise (PHPUnit, Codeception internal machinery), strips vendor frames by default, and bounds trace depth to `max_frames`.

### SourceExcerpt
Captures an asymmetric window of source lines around the failing line of code in the first non-vendor frame (configurable lines before, two lines after) to provide immediate context on the failing assertion and setup.

### ScenarioExtractor
Extracts executed steps from `ScenarioDriven` tests, highlighting which step failed and normalizing step file locations.

### PathNormalizer
Normalizes OS-dependent file paths (e.g. Windows backslashes) into canonical forward-slash, project-relative paths.

### TextReportFormatter
Renders structured `AiReport` data into plain-text failure summaries (`ai-report.txt`).

### ConsoleText
Utility module providing string truncation (from the middle to preserve assertion ends) and terminal string escaping.
