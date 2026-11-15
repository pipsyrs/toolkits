<?php

namespace App\Support;

// #rrggbb helpers; WCAG relative luminance and contrast ratio.
final class Color
{
    public const HEX_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    private const INK = '#15181d';

    private const WHITE = '#ffffff';

    /**
     * @return array{int, int, int}
     */
    public static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    public static function luminance(string $hex): float
    {
        $channels = array_map(function (int $value) {
            $c = $value / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    public static function contrast(string $a, string $b): float
    {
        [$light, $dark] = [max(self::luminance($a), self::luminance($b)), min(self::luminance($a), self::luminance($b))];

        return ($light + 0.05) / ($dark + 0.05);
    }

    // Ink or white, whichever reads better on $background.
    public static function readableOn(string $background): string
    {
        return self::contrast(self::INK, $background) >= self::contrast(self::WHITE, $background) ? self::INK : self::WHITE;
    }
}
