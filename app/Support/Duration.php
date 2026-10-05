<?php

namespace App\Support;

/**
 * Lesson lengths as the curriculum editor shows them: "12:40" (minutes:seconds) ↔ 760 seconds.
 */
class Duration
{
    public const PATTERN = '/^\d{1,3}:[0-5]\d$/';

    public static function toSeconds(?string $duration): int
    {
        if ($duration === null || ! preg_match(self::PATTERN, trim($duration))) {
            return 0;
        }

        [$minutes, $seconds] = array_map('intval', explode(':', trim($duration)));

        return $minutes * 60 + $seconds;
    }

    public static function format(int $seconds): string
    {
        return $seconds > 0 ? sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60) : '';
    }
}
