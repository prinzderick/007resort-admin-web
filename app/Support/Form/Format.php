<?php

namespace App\Support\Form;

/** Server-side twins of the JS display helpers (resources/js/form/lib.js). Money stays a string: no floats. */
final class Format
{
    /** "1234567.5" -> "1,234,567.50"; trailing zeros beyond $minDecimals are trimmed ("1000.1250" -> "1,000.125"). */
    public static function money(string $value, int $minDecimals = 2): string
    {
        $value = trim($value);
        if ($value === '' || ! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            return $value;
        }
        $neg = str_starts_with($value, '-');
        [$int, $frac] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');
        $frac = str_pad(rtrim($frac, '0'), $minDecimals, '0');
        $int = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', ltrim($int, '0') === '' ? '0' : ltrim($int, '0'));

        return ($neg ? '-' : '').$int.($frac !== '' ? '.'.$frac : '');
    }

    /** "10 minutes", "1 day 2 hours", "45 seconds". $seconds may be int or numeric string. */
    public static function duration(int|float|string $seconds): string
    {
        $s = (int) round((float) $seconds);
        if ($s === 0) {
            return '0 minutes';
        }
        $parts = [];
        foreach (['day' => 86400, 'hour' => 3600, 'minute' => 60, 'second' => 1] as $name => $size) {
            $n = intdiv($s, $size);
            if ($n > 0) {
                $parts[] = $n.' '.$name.($n === 1 ? '' : 's');
                $s -= $n * $size;
            }
        }

        return implode(' ', array_slice($parts, 0, 2));
    }

    /** "2026-09-24" -> "24 Sep 2026" (day first, as staff write it). */
    public static function date(string $iso): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $iso);

        return $d && $d->format('Y-m-d') === $iso ? $d->format('j M Y') : $iso;
    }

    /** "13:05" -> "1:05 PM". */
    public static function time12(string $t): string
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m)) {
            return $t;
        }
        $h = (int) $m[1];
        if ($h === 24) {
            return '12:00 AM';
        }

        return ($h % 12 === 0 ? 12 : $h % 12).':'.$m[2].' '.($h >= 12 ? 'PM' : 'AM');
    }
}
