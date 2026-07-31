<?php

declare(strict_types=1);

namespace WebProject\Codeception\Module\AiReporter\Util;

use function intdiv;
use function max;
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

        // Below this there is no room for an ellipsis plus one character per
        // side, and a zero-length tail would make mb_substr() return the rest
        // of the string instead of nothing.
        if ($maxLength < 5) {
            return mb_substr($message, 0, max(0, $maxLength));
        }

        $keep = intdiv($maxLength - 3, 2);

        return mb_substr($message, 0, $maxLength - 3 - $keep) . '...' . mb_substr($message, -$keep);
    }
}
