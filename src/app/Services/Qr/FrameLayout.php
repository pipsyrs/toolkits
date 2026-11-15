<?php

namespace App\Services\Qr;

use App\Enums\QrFrame;
use App\Support\Color;

// Frame geometry shared by the PNG and SVG renderers, scaled from the QR side (1u = 1% of it).
final readonly class FrameLayout
{
    // GD measures and draws at 96 dpi: 1 px = 0.75 pt.
    private const PX_TO_PT = 0.75;

    // Inter cap height / em, used to centre a line on its band.
    private const CAP_HEIGHT = 0.727;

    /**
     * @param  list<array{x: int, y: int, w: int, h: int, r: int, fill: string}>  $rects  painted in order
     * @param  list<array{text: string, x: int, cx: int, baseline: int, pt: float, px: float, width: int, color: string}>  $texts
     */
    private function __construct(
        public int $width,
        public int $height,
        public int $qrX,
        public int $qrY,
        public int $qrSide,
        public array $rects,
        public array $texts,
    ) {}

    public static function font(): string
    {
        return resource_path('fonts/Inter-SemiBold.ttf');
    }

    public static function for(QrStyle $style, int $qrSide): self
    {
        $u = $qrSide / 100;

        return match ($style->frame) {
            QrFrame::None => new self($qrSide, $qrSide, 0, 0, $qrSide, [], []),
            QrFrame::Simple => self::simple($style, $qrSide, $u),
            QrFrame::Banner => self::banner($style, $qrSide, $u),
            QrFrame::Card => self::card($style, $qrSide, $u),
        };
    }

    public function isBare(): bool
    {
        return $this->rects === [];
    }

    private static function simple(QrStyle $style, int $side, float $u): self
    {
        [$pad, $band, $line] = [self::px(5 * $u), self::px(15 * $u), max(2, self::px(0.8 * $u))];
        $top = $style->title !== '' ? $band : $pad;
        $bottom = $style->footer !== '' ? $band : $pad;
        [$w, $h] = [$side + 2 * $pad, $top + $side + $bottom];
        $c = $style->colors;

        return new self($w, $h, $pad, $top, $side, [
            self::rect(0, 0, $w, $h, 0, $c['frame']),
            self::rect($line, $line, $w - 2 * $line, $h - 2 * $line, 0, $c['bg']),
        ], self::lines([
            [$style->title, $w / 2, ($top + $line) / 2, 7 * $u, $w - 2 * $pad, $c['dark']],
            [$style->footer, $w / 2, $h - ($bottom + $line) / 2, 7 * $u, $w - 2 * $pad, $c['dark']],
        ]));
    }

    private static function banner(QrStyle $style, int $side, float $u): self
    {
        [$pad, $band, $margin] = [self::px(6 * $u), self::px(17 * $u), self::px(1.5 * $u)];
        $top = $style->title !== '' ? $band : $pad;
        $bottom = $style->footer !== '' ? $band : $pad;
        [$w, $h] = [$side + 2 * $pad, $top + $side + $bottom];
        $c = $style->colors;
        $ink = Color::readableOn($c['frame']);

        return new self($w, $h, $pad, $top, $side, [
            self::rect(0, 0, $w, $h, self::px(4 * $u), $c['frame']),
            self::rect($pad - $margin, $top - $margin, $side + 2 * $margin, $side + 2 * $margin, self::px(2 * $u), $c['bg']),
        ], self::lines([
            [$style->title, $w / 2, ($top - $margin) / 2, 8 * $u, $w - 2 * $pad, $ink],
            [$style->footer, $w / 2, $h - ($bottom - $margin) / 2, 8 * $u, $w - 2 * $pad, $ink],
        ]));
    }

    private static function card(QrStyle $style, int $side, float $u): self
    {
        [$pad, $radius, $line] = [self::px(7 * $u), self::px(7 * $u), max(2, self::px(1.2 * $u))];
        $top = $style->title !== '' ? self::px(16 * $u) : $pad;
        $bottom = $style->footer !== '' ? self::px(22 * $u) : $pad;
        [$w, $h] = [$side + 2 * $pad, $top + $side + $bottom];
        $c = $style->colors;

        $rects = [
            self::rect(0, 0, $w, $h, $radius, $c['frame']),
            self::rect($line, $line, $w - 2 * $line, $h - 2 * $line, $radius - $line, $c['bg']),
        ];
        $texts = self::lines([[$style->title, $w / 2, ($top + $line) / 2, 7.5 * $u, $w - 2 * $pad, $c['dark']]]);

        // Footer sits in a pill of the frame color, sized to its text.
        if ($style->footer !== '') {
            $cy = $top + $side + ($bottom - $line) / 2;
            $footer = self::line($style->footer, $w / 2, $cy, 6 * $u, $w - 2 * $pad - 10 * $u, Color::readableOn($c['frame']));
            $pillH = self::px(12 * $u);
            $pillW = min($w - 2 * $pad, $footer['width'] + self::px(10 * $u));
            $rects[] = self::rect(self::px(($w - $pillW) / 2), self::px($cy - $pillH / 2), $pillW, $pillH, intdiv($pillH, 2), $c['frame']);
            $texts[] = $footer;
        }

        return new self($w, $h, $pad, $top, $side, $rects, $texts);
    }

    /**
     * @return array{x: int, y: int, w: int, h: int, r: int, fill: string}
     */
    private static function rect(int $x, int $y, int $w, int $h, int $r, string $fill): array
    {
        return ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'r' => max(0, $r), 'fill' => $fill];
    }

    /**
     * @param  list<array{string, float, float, float, float, string}>  $specs  [text, cx, cy, maxPx, maxWidth, color]
     * @return list<array{text: string, x: int, cx: int, baseline: int, pt: float, px: float, width: int, color: string}>
     */
    private static function lines(array $specs): array
    {
        $specs = array_filter($specs, fn (array $spec) => $spec[0] !== '');

        return array_values(array_map(fn (array $spec) => self::line(...$spec), $specs));
    }

    // One centred line, shrunk proportionally when it is wider than $maxWidth.
    private static function line(string $text, float $cx, float $cy, float $maxPx, float $maxWidth, string $color): array
    {
        $pt = $maxPx * self::PX_TO_PT;
        [$left, $width] = self::measure($text, $pt);

        if ($width > $maxWidth) {
            $pt *= $maxWidth / $width;
            [$left, $width] = self::measure($text, $pt);
        }

        return [
            'text' => $text,
            'x' => self::px($cx - $width / 2 - $left),
            'cx' => self::px($cx),
            'baseline' => self::px($cy + $pt / self::PX_TO_PT * self::CAP_HEIGHT / 2),
            'pt' => $pt,
            'px' => $pt / self::PX_TO_PT,
            'width' => $width,
            'color' => $color,
        ];
    }

    /**
     * @return array{int, int} left bearing and ink width in px
     */
    private static function measure(string $text, float $pt): array
    {
        $box = imagettfbbox($pt, 0, self::font(), $text) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        $xs = [$box[0], $box[2], $box[4], $box[6]];

        return [min($xs), max(1, max($xs) - min($xs))];
    }

    private static function px(float $value): int
    {
        return (int) round($value);
    }
}
