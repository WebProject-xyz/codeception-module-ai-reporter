<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Report;

use function array_reverse;
use Codeception\Test\Interfaces\ScenarioDriven;
use Codeception\Test\Test;
use function count;

/**
 * @phpstan-import-type ScenarioStep from ReportTypes
 */
final class ScenarioExtractor
{
    public function __construct(private readonly PathNormalizer $pathNormalizer)
    {
    }

    /**
     * @return array<int, ScenarioStep>
     */
    public function extract(Test $test, int $maxFrames): array
    {
        if (!$test instanceof ScenarioDriven) {
            return [];
        }

        $scenario = $test->getScenario();
        $steps    = [];
        foreach (array_reverse($scenario->getSteps()) as $step) {
            $label = (string) $step;
            if ('' === $label) {
                continue;
            }

            $entry = [
                'step'   => $label,
                'failed' => $step->hasFailed(),
            ];

            $file = $step->getFilePath();
            if (null !== $file && '' !== $file) {
                $normalizedFile = $this->pathNormalizer->normalize($file);
                if ('' !== $normalizedFile) {
                    $entry['file'] = $normalizedFile;
                }
            }

            $line = $step->getLineNumber();
            if (null !== $line) {
                $entry['line'] = $line;
            }

            $steps[] = $entry;
            if (count($steps) >= $maxFrames) {
                break;
            }
        }

        return $steps;
    }
}
