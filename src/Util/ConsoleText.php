<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Util;

use function strlen;
use function strtr;
use function substr;
use Symfony\Component\Console\Formatter\OutputFormatter;

final class ConsoleText
{
    public function escape(string $message): string
    {
        return OutputFormatter::escape(strtr($message, ["\n" => '\\n', "\r" => '\\r']));
    }

    public function truncate(string $message, int $maxLength = 260): string
    {
        if (strlen($message) <= $maxLength) {
            return $message;
        }

        return substr($message, 0, $maxLength - 3) . '...';
    }
}
