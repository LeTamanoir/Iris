<?php

declare(strict_types=1);

namespace Iris;

use InvalidArgumentException;

final class Duration
{
    const int Nanosecond = 1;

    const int Microsecond = 1000 * self::Nanosecond;

    const int Millisecond = 1000 * self::Microsecond;

    const int Second = 1000 * self::Millisecond;

    const int Minute = 60 * self::Second;

    const int Hour = 60 * self::Minute;

    /**
     * Parse a duration string into a number of milliseconds.
     */
    public static function parse(string $d): int
    {
        $d = trim($d);
        if ($d === '') {
            throw new InvalidArgumentException('Empty duration');
        }

        $sign = 1;
        if ($d[0] === '+' || $d[0] === '-') {
            if ($d[0] === '-')
                $sign = -1;
            $d = substr($d, 1);
            if ($d === '')
                throw new InvalidArgumentException('Invalid duration');
        }

        // Whole string must be a concatenation of <num><unit> tokens, no spaces.
        // num: integer or float; unit: ns|us|ms|s|m|h
        if (!preg_match('/^(\d+(?:\.\d+)?(?:ns|us|ms|s|m|h))+$/', $d)) {
            throw new InvalidArgumentException('Invalid duration syntax: ' . $d);
        }

        // Extract tokens
        preg_match_all('/(\d+(?:\.\d+)?)(ns|us|ms|s|m|h)/', $d, $m, PREG_SET_ORDER);

        $unitToSeconds = [
            'ns' => self::Nanosecond,
            'us' => self::Microsecond,
            'ms' => self::Millisecond,
            's' => self::Second,
            'm' => self::Minute,
            'h' => self::Hour,
        ];

        $total = 0;
        foreach ($m as $tok) {
            $num = (float) $tok[1];
            $unit = $tok[2];
            $total += (int) ($num * $unitToSeconds[$unit]);
        }

        return $sign * $total;
    }
}
