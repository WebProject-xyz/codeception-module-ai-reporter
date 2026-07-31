<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Util;

use function mb_strimwidth;
use function strtr;
use Symfony\Component\Console\Formatter\OutputFormatter;

final class ConsoleText
{
    public function escape(string $message): string
    {
        return OutputFormatter::escape(strtr($message, ["\n" => '\\n', "\r" => '\\r']));
    }

    public function truncate(string $message, int $maxLength = 260): string
    {
        return mb_strimwidth($message, 0, $maxLength, '...');
    }
}
