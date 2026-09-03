<?php

declare(strict_types=1);

namespace App\Support;

final class Rupiah
{
    /** 400000000 -> "Rp400.000.000" ; negatif -> "-Rp1.000" */
    public static function format(int|float|string|null $value, bool $prefix = true): string
    {
        $n = (int) round((float) ($value ?? 0));
        $sign = $n < 0 ? '-' : '';
        $abs = number_format(abs($n), 0, ',', '.');

        return $sign.($prefix ? 'Rp' : '').$abs;
    }

    /** 11.0 -> "11%" ; 1.75 -> "1,75%" */
    public static function persen(int|float|string|null $value, int $maxDecimals = 3): string
    {
        $f = (float) ($value ?? 0);
        $s = rtrim(rtrim(number_format($f, $maxDecimals, ',', '.'), '0'), ',');

        return ($s === '' ? '0' : $s).'%';
    }
}
