<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Tests\Support\Fixture;

use Codeception\Lib\Console\Output;

/**
 * Buffers console writes so tests can assert on inline AI Context output.
 */
final class CapturingOutput extends Output
{
    private string $buffer = '';

    public function __construct()
    {
        parent::__construct(['colors' => false, 'interactive' => false]);
    }

    protected function doWrite(string $message, bool $newline): void
    {
        $this->buffer .= $message;
        if ($newline) {
            $this->buffer .= "\n";
        }
    }

    public function fetch(): string
    {
        $out          = $this->buffer;
        $this->buffer = '';

        return $out;
    }
}
