<?php

namespace App\Support;

/**
 * Money is a decimal STRING end to end (API contract). All arithmetic uses
 * bcmath; a float never touches an amount.
 */
final class Money
{
    public const SCALE = 4;

    public static function norm(mixed $v): string
    {
        $v = is_string($v) || is_int($v) ? (string) $v : '0';

        return preg_match('/^-?\d+(\.\d{1,4})?$/', $v) ? $v : '0';
    }

    public static function add(mixed $a, mixed $b): string
    {
        return bcadd(self::norm($a), self::norm($b), self::SCALE);
    }

    public static function sub(mixed $a, mixed $b): string
    {
        return bcsub(self::norm($a), self::norm($b), self::SCALE);
    }

    /** @param  iterable<mixed>  $values */
    public static function sum(iterable $values): string
    {
        $t = '0.0000';
        foreach ($values as $v) {
            $t = self::add($t, $v);
        }

        return $t;
    }

    public static function cmp(mixed $a, mixed $b): int
    {
        return bccomp(self::norm($a), self::norm($b), self::SCALE);
    }

    /** Display: NGN 1,500.00 (half-up to 2 dp, thousands separated). */
    public static function format(mixed $v, string $currency = 'NGN'): string
    {
        $v = self::norm($v);
        $neg = str_starts_with($v, '-');
        $abs = ltrim($v, '-');
        $rounded = bcadd(bcadd($abs, '0.005', 4), '0', 2);
        [$int, $dec] = explode('.', $rounded);
        $int = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $int);
        $symbol = $currency === 'NGN' ? "\u{20A6}" : $currency.' ';

        return ($neg && bccomp($rounded, '0', 2) !== 0 ? '-' : '').$symbol.$int.'.'.$dec;
    }
}
