<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Support\Fixture;

use Codeception\Test\Metadata;
use Codeception\Test\Test;

/**
 * Minimal Codeception Test used to drive FailEvent dispatching in unit tests.
 */
final class StubTest extends Test
{
    public function __construct(
        string $name,
        string $filename,
        private readonly string $signature,
    ) {
        $metadata = new Metadata();
        $metadata->setName($name);
        $metadata->setFilename($filename);
        $this->setMetadata($metadata);
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
}
