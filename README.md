# AI Codeception Reporter

[![CI](https://github.com/WebProject-xyz/codeception-module-ai-reporter/actions/workflows/ci.yml/badge.svg)](https://github.com/WebProject-xyz/codeception-module-ai-reporter/actions/workflows/ci.yml)
[![Release](https://github.com/WebProject-xyz/codeception-module-ai-reporter/actions/workflows/release.yml/badge.svg)](https://github.com/WebProject-xyz/codeception-module-ai-reporter/actions/workflows/release.yml)
[![PHP Version](https://img.shields.io/packagist/php-v/WebProject-xyz/codeception-module-ai-reporter)](https://packagist.org/packages/WebProject-xyz/codeception-module-ai-reporter)
[![Latest Stable Version](https://img.shields.io/packagist/v/WebProject-xyz/codeception-module-ai-reporter)](https://packagist.org/packages/WebProject-xyz/codeception-module-ai-reporter)
[![Total Downloads](https://img.shields.io/packagist/dt/WebProject-xyz/codeception-module-ai-reporter)](https://packagist.org/packages/WebProject-xyz/codeception-module-ai-reporter)
[![License](https://img.shields.io/packagist/l/WebProject-xyz/codeception-module-ai-reporter)](https://packagist.org/packages/WebProject-xyz/codeception-module-ai-reporter)

> **Give your AI coding agent everything it needs to fix a failing test — in a single run.**

A [Codeception 5](https://codeception.com) extension that captures structured, deterministic failure context and writes it as machine-readable JSON and plain-text artifacts. Built for the fix-in-a-loop workflow: agent runs tests, reads the report, patches code, repeats — without ever needing a human in the loop.

---

## Why this exists

When an AI agent encounters a failing Codeception test, the default output is a wall of terminal text: ANSI escape codes, PHPUnit XML noise, interleaved suite headers, and a stack trace that buries the actual problem. Agents waste tokens parsing noise instead of fixing bugs.

This extension solves that by producing **two clean, stable output files** after every test run:

- **`ai-report.json`** — structured data ready for programmatic consumption
- **`ai-report.txt`** — compact human-and-agent-readable summary

Every failure record contains exactly what an agent needs:

| Field | What it gives the agent |
|---|---|
| `exception.message` | The plain-English failure reason |
| `exception.comparison_diff` | A unified diff when values don't match — no more guessing what changed |
| `trace` | Cleaned stack frames, vendor noise removed, capped to a useful depth |
| `scenario_steps` | The Codeception steps leading up to the failure |
| `hints` | Pre-computed triage suggestions (assertion mismatch, missing element, etc.) |
| `artifacts` | Paths to screenshots, HAR files, and other test artifacts |

With the `--report` flag, the same context is also **printed inline** in the terminal output immediately after each failure — useful for agents that read stdout directly.

---

## Installation

```bash
composer require --dev WebProject-xyz/codeception-module-ai-reporter
```

Requires PHP `^8.3` and Codeception `^5.3.5`.

---

## Setup

Add the extension to your `codeception.yml`:

```yaml
extensions:
    enabled:
        - WebProject\Codeception\Module\AiReporter\Extension\AiReporter:
              format: both          # text | json | both
              output: tests/_output
              max_frames: 8
              include_steps: true
              include_artifacts: true
              compact_paths: true
```

---

## Usage

### Standard run

```bash
vendor/bin/codecept run
```

Report files are written to `tests/_output/` after every run, regardless of whether tests pass or fail.

### Agent run mode (`--report`)

```bash
vendor/bin/codecept run --report
```

Enables **inline AI context** — a structured block printed directly below each failure in the terminal:

```
  AI Context
    Exception: PHPUnit\Framework\ExpectationFailedException
    Message: Failed asserting that two strings are identical.
    Diff:
      --- Expected
      +++ Actual
      @@ @@
      -'expected-value'
      +'actual-value'
    Trace:
      #1 tests/Unit/MyTest.php:42 MyTest->testSomething
    Hints:
      - Assertion mismatch detected; compare expected and actual values at the top non-vendor frame.
```

### Recommended agent instruction

Drop this into your agent's system prompt or task description:

```
Run `vendor/bin/codecept run --report` and use the inline AI Context
plus tests/_output/ai-report.json to identify and fix failures.
Repeat until all tests pass.
```

---

## Output format

### JSON (`ai-report.json`)

A machine-readable schema is available at [`schema/ai-report.schema.json`](schema/ai-report.schema.json) (JSON Schema 2020-12).



```jsonc
{
  "run": {
    "generated_at": "2026-02-19T12:00:00+00:00",
    "duration_seconds": 1.23,
    "project_root": "/repo/project",
    "output_dir": "/repo/project/tests/_output"
  },
  "summary": {
    "tests": 10, "successful": 9, "failures": 1,
    "errors": 0, "warnings": 0, "assertions": 42,
    "successful_run": false
  },
  "failures": [
    {
      "status": "failure",
      "suite": "Unit",
      "test": {
        "display_name": "MyTest: check value",
        "signature": "MyTest:checkValue",
        "file": "tests/Unit/MyTest.php"
      },
      "exception": {
        "class": "PHPUnit\\Framework\\ExpectationFailedException",
        "message": "Failed asserting that two strings are identical.",
        "comparison_expected": "'expected-value'",
        "comparison_actual": "'actual-value'",
        "comparison_diff": "\n--- Expected\n+++ Actual\n@@ @@\n-'expected-value'\n+'actual-value'\n",
        "previous": []
      },
      "scenario_steps": [],
      "trace": [
        { "file": "tests/Unit/MyTest.php", "line": 42, "call": "MyTest->checkValue" }
      ],
      "artifacts": {},
      "hints": [
        "Assertion mismatch detected; compare expected and actual values at the top non-vendor frame."
      ]
    }
  ]
}
```

### Text (`ai-report.txt`)

```
Context
generated_at: 2026-02-19T12:00:00+00:00
project_root: /repo/project
totals: tests=10 successful=9 failures=1 errors=0 warnings=0 skipped=0 incomplete=0 useless=0 assertions=42

Failure 1
status: failure
suite: Unit
test: MyTest: check value
test_file: tests/Unit/MyTest.php
test_signature: MyTest:checkValue

Exception
exception_class: PHPUnit\Framework\ExpectationFailedException
message: Failed asserting that two strings are identical.
comparison_expected: 'expected-value'
comparison_actual: 'actual-value'
comparison_diff:
--- Expected
+++ Actual
@@ @@
-'expected-value'
+'actual-value'

Scenario
none

Trace
#1 tests/Unit/MyTest.php:42 MyTest->checkValue

Hints
- Assertion mismatch detected; compare expected and actual values at the top non-vendor frame.
```

---

## Configuration reference

| Option | Type | Default | Description |
|---|---|---|---|
| `format` | `text\|json\|both` | `both` | Which report files to write |
| `output` | `string` | `tests/_output` | Output directory for report files |
| `max_frames` | `int` | `8` | Maximum stack frames per failure |
| `include_steps` | `bool` | `true` | Include Codeception scenario steps |
| `include_artifacts` | `bool` | `true` | Include test metadata artifacts (screenshots, etc.) |
| `compact_paths` | `bool` | `true` | Use project-relative paths where possible |

---

## Platform support

- Linux, macOS, and Windows paths are all handled correctly.
- Paths are normalized to forward slashes in report output for consistency across platforms.

---

## Contributing

Contributions are welcome. Please open an issue before submitting large changes.

```bash
composer test:build   # rebuild Codeception actor classes
composer test         # run tests
composer stan         # PHPStan static analysis (level 7)
composer cs:check     # check code style
composer cs:fix       # auto-fix code style
```

---

## License

MIT
