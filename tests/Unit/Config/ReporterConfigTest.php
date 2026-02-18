<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Unit\Config;

use Codeception\Test\Unit;
use InvalidArgumentException;
use WebProject\Codeception\Module\AiReporter\Config\ReporterConfig;

final class ReporterConfigTest extends Unit
{
    public function testDefaultsUseProvidedOutputDirectory(): void
    {
        $config = ReporterConfig::fromArray([], '/tmp/default-output', '/repo/project');

        self::assertTrue($config->wantsJson());
        self::assertTrue($config->wantsText());
        self::assertSame('/tmp/default-output', $config->outputDir());
        self::assertSame(8, $config->maxFrames());
        self::assertTrue($config->includeSteps());
        self::assertTrue($config->includeArtifacts());
        self::assertTrue($config->compactPaths());
    }

    public function testRelativeOutputIsResolvedAgainstProjectRoot(): void
    {
        $config = ReporterConfig::fromArray(['output' => 'tests/_output'], '/tmp/default-output', '/repo/project');

        self::assertSame('/repo/project/tests/_output', $config->outputDir());
    }

    public function testInvalidFormatThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid `format`');

        // @phpstan-ignore-next-line
        ReporterConfig::fromArray(['format' => 'xml'], '/tmp/default-output', '/repo/project');
    }

    public function testInvalidMaxFramesThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid `max_frames`');

        // @phpstan-ignore-next-line
        ReporterConfig::fromArray(['max_frames' => 0], '/tmp/default-output', '/repo/project');
    }

    public function testRelativeOutputWithWindowsProjectRootIsResolved(): void
    {
        $config = ReporterConfig::fromArray(['output' => 'tests\\_output'], '/tmp/default-output', 'C:\\repo\\project\\');

        self::assertSame('C:\\repo\\project/tests\\_output', $config->outputDir());
    }

    public function testAbsoluteWindowsPathWithTrailingBackslashIsStripped(): void
    {
        $config = ReporterConfig::fromArray(['output' => 'C:\\reports\\'], '/tmp/default-output', 'C:\\repo\\project');

        self::assertSame('C:\\reports', $config->outputDir());
    }

    public function testUncOutputPathIsHandledAsAbsolute(): void
    {
        $config = ReporterConfig::fromArray(['output' => '\\\\server\\share\\reports'], '/tmp/default-output', 'C:\\repo\\project');

        self::assertSame('\\\\server\\share\\reports', $config->outputDir());
    }
}
