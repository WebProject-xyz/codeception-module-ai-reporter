<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Util;

use function intdiv;
use function mb_strlen;
use function mb_substr;
use function strtr;
use Symfony\Component\Console\Formatter\OutputFormatter;

final class ConsoleText
{
    public function escape(string $message): string
    {
        return OutputFormatter::escape(strtr($message, ["\n" => '\\n', "\r" => '\\r']));
    }

    /**
     * Shorten from the middle. Assertion messages carry their payload at both
     * ends — what was expected up front, what was actually found at the back —
     * so cutting the tail loses the half that usually explains the failure.
     */
    public function truncate(string $message, int $maxLength = 260): string
    {
        if (mb_strlen($message) <= $maxLength) {
            return $message;
        }

        $keep = intdiv($maxLength - 3, 2);

        return mb_substr($message, 0, $maxLength - 3 - $keep) . '...' . mb_substr($message, -$keep);
    }
}
