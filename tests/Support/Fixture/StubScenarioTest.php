<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Support\Fixture;

use Codeception\Scenario;
use Codeception\Test\Interfaces\ScenarioDriven;
use Codeception\Test\Metadata;
use Codeception\Test\Test;

final class StubScenarioTest extends Test implements ScenarioDriven
{
    private Scenario $scenario;

    public function __construct(
        string $name,
        string $filename,
        private readonly string $signature,
    ) {
        $metadata = new Metadata();
        $metadata->setName($name);
        $metadata->setFilename($filename);
        $this->setMetadata($metadata);
        $this->scenario = new Scenario($this);
    }

    public function test(): void
    {
    }

    public function run(): void
    {
    }

    public function toString(): string
    {
        return $this->getName();
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function getFeature(): ?string
    {
        return null;
    }

    public function getScenario(): Scenario
    {
        return $this->scenario;
    }

    public function getScenarioText(string $format = 'text'): string
    {
        return '';
    }

    public function preload(): void
    {
    }

    public function getSourceCode(): string
    {
        return '';
    }
}
