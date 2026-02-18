<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Unit\Report;

use Codeception\Test\Unit;
use function count;
use RuntimeException;
use TypeError;
use WebProject\Codeception\Module\AiReporter\Report\HintGenerator;

final class HintGeneratorTest extends Unit
{
    public function testGeneratesAssertionHint(): void
    {
        $generator = new HintGenerator();

        $hints = $generator->generate(
            new RuntimeException('Failed asserting that two values are equal'),
            [['file' => 'src/Service/Foo.php', 'line' => 10, 'call' => 'App\\Foo->bar']],
            []
        );

        self::assertNotEmpty($hints);
        self::assertStringContainsString('Assertion mismatch', $hints[0]);
    }

    public function testGeneratesScenarioHintWhenStepsExist(): void
    {
        $generator = new HintGenerator();

        $hints = $generator->generate(
            new TypeError('Bad type'),
            [['file' => 'src/Service/Foo.php', 'line' => 10, 'call' => 'App\\Foo->bar']],
            [['step' => 'click "Login"', 'failed' => true]]
        );

        self::assertGreaterThanOrEqual(2, count($hints));
        self::assertStringContainsString('last scenario step', implode(' ', $hints));
    }
}
