<?php

namespace App\Support;

// Axis math and id-ID number formatting for server-rendered charts.
class ChartAxis
{
    // Smallest 1/2/5 x 10^k that is >= $max (at least 1).
    public static function niceMax(int|float $max): int
    {
        if ($max <= 1) {
            return 1;
        }

        $power = 10 ** floor(log10($max));

        foreach ([1, 2, 5, 10] as $step) {
            if ($step * $power >= $max) {
                return (int) ($step * $power);
            }
        }

        return (int) (10 * $power);
    }

    public static function format(int|float $value, string $format = 'number', bool $compact = false): string
    {
        $value = (int) round($value);
        $prefix = $format === 'rupiah' ? 'Rp' : '';

        if (! $compact || abs($value) < 1000) {
            return $prefix.number_format($value, 0, ',', '.');
        }

        foreach ([1_000_000_000 => 'M', 1_000_000 => 'jt', 1_000 => 'rb'] as $unit => $suffix) {
            if (abs($value) >= $unit) {
                $scaled = $value / $unit;
                $digits = abs($scaled) < 10 && fmod($scaled, 1) !== 0.0 ? 1 : 0;

                return $prefix.number_format($scaled, $digits, ',', '.').' '.$suffix;
            }
        }

        return $prefix.$value;
    }

    /**
     * About $count evenly spaced indices across $n points, always including both ends.
     *
     * @return list<int>
     */
    public static function ticks(int $n, int $count = 5): array
    {
        // Few points: label every one.
        if ($n <= 8) {
            return range(0, max(0, $n - 1));
        }

        $step = ($n - 1) / ($count - 1);

        return array_values(array_unique(array_map(fn ($i) => (int) round($i * $step), range(0, $count - 1))));
    }
}
