<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

/** The API speaks UTC; this converts for display only. */
final class Time
{
    public static function parse(?string $iso): ?CarbonImmutable
    {
        if ($iso === null || $iso === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($iso, 'UTC');
        } catch (Throwable) {
            return null;
        }
    }

    public static function format(?string $iso, string $format = 'd M Y, H:i'): string
    {
        $t = self::parse($iso);

        return $t ? $t->setTimezone((string) config('r007.display_timezone', 'Africa/Lagos'))->format($format) : "\u{2014}";
    }

    public static function ago(?string $iso): string
    {
        $t = self::parse($iso);

        return $t ? $t->diffForHumans() : 'never';
    }

    public static function today(): string
    {
        return CarbonImmutable::now((string) config('r007.display_timezone', 'Africa/Lagos'))->toDateString();
    }
}
