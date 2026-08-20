<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Unit\Report;

use Codeception\Step;
use Codeception\Test\Unit;
use WebProject\Codeception\Module\AiReporter\Report\ScenarioExtractor;
use WebProject\Codeception\Module\AiReporter\Tests\Support\Fixture\PathNormalizerFactory;
use WebProject\Codeception\Module\AiReporter\Tests\Support\Fixture\StubScenarioTest;
use WebProject\Codeception\Module\AiReporter\Tests\Support\Fixture\StubTest;

final class ScenarioExtractorTest extends Unit
{
    public function testExtractReturnsEmptyArrayForNonScenarioDrivenTest(): void
    {
        $extractor = new ScenarioExtractor(PathNormalizerFactory::make());
        $test      = new StubTest('name', '/path/test.php', 'sig');

        self::assertSame([], $extractor->extract($test, 8));
    }

    public function testExtractReturnsStepsInReverseOrderWithNormalizedPathsAndFailureFlags(): void
    {
        $projectRoot = (string) realpath(__DIR__ . '/../../../../');
        $filePath    = $projectRoot . '/tests/Unit/LoginCest.php';
        $extractor   = new ScenarioExtractor(PathNormalizerFactory::make(projectRoot: $projectRoot));
        $test        = new StubScenarioTest('name', $filePath, 'sig');

        /** @var array<string> $args1 */
        $args1 = ['/login'];
        $step1 = new class('amOnPage', $args1, $filePath) extends Step {
            public function __construct(string $action, array $arguments, string $filePath)
            {
                parent::__construct($action, $arguments);
                $this->file   = $filePath;
                $this->line   = 10;
                $this->failed = false;
            }
        };

        /** @var array<string> $args2 */
        $args2 = ['username', 'admin'];
        $step2 = new class('fillField', $args2, $filePath) extends Step {
            public function __construct(string $action, array $arguments, string $filePath)
            {
                parent::__construct($action, $arguments);
                $this->file   = $filePath;
                $this->line   = 12;
                $this->failed = true;
            }
        };

        $test->getScenario()->addStep($step1);
        $test->getScenario()->addStep($step2);

        $extracted = $extractor->extract($test, 8);

        self::assertCount(2, $extracted);
        self::assertSame('fill field "username","admin"', $extracted[0]['step']);
        self::assertTrue($extracted[0]['failed']);
        self::assertSame('../tests/Unit/LoginCest.php', $extracted[0]['file'] ?? null);
        self::assertSame(12, $extracted[0]['line'] ?? null);

        self::assertSame('am on page "/login"', $extracted[1]['step']);
        self::assertFalse($extracted[1]['failed']);
        self::assertSame('../tests/Unit/LoginCest.php', $extracted[1]['file'] ?? null);
        self::assertSame(10, $extracted[1]['line'] ?? null);
    }

    public function testExtractRespectsMaxFramesLimit(): void
    {
        $extractor = new ScenarioExtractor(PathNormalizerFactory::make());
        $test      = new StubScenarioTest('name', '/repo/project/tests/LoginCest.php', 'sig');

        for ($i = 1; $i <= 5; ++$i) {
            $test->getScenario()->addStep(new class('step' . $i) extends Step {});
        }

        $extracted = $extractor->extract($test, 2);

        self::assertCount(2, $extracted);
        self::assertSame('step5', $extracted[0]['step']);
        self::assertSame('step4', $extracted[1]['step']);
    }

    public function testExtractSkipsStepsWithEmptyLabel(): void
    {
        $extractor = new ScenarioExtractor(PathNormalizerFactory::make());
        $test      = new StubScenarioTest('name', '/repo/project/tests/LoginCest.php', 'sig');

        $emptyStep = new class('') extends Step {};
        $validStep = new class('validAction') extends Step {};

        $test->getScenario()->addStep($emptyStep);
        $test->getScenario()->addStep($validStep);

        $extracted = $extractor->extract($test, 8);

        self::assertCount(1, $extracted);
        self::assertSame('valid action', $extracted[0]['step']);
    }
}
