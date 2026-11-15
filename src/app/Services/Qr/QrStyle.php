<?php

namespace App\Services\Qr;

use App\Enums\QrFrame;
use App\Enums\QrTemplate;
use App\Support\Color;
use InvalidArgumentException;

// Look of one QR render: template shape, colors and optional frame text.
// Values end up in SVG attributes and text, so they are checked here and not only in the form.
final readonly class QrStyle
{
    public const COLOR_KEYS = ['dark', 'eye', 'bg', 'frame'];

    public const MAX_FRAME_TEXT = 40;

    public const MIN_SCAN_CONTRAST = 4.0;

    // Control/format/unassigned code points: invalid in XML or invisible in the image.
    public const FORBIDDEN_TEXT = '/\p{C}/u';

    public string $title;

    public string $footer;

    /**
     * @param  array{dark: string, eye: string, bg: string, frame: string}  $colors
     */
    public function __construct(
        public QrTemplate $template,
        public array $colors,
        public QrFrame $frame = QrFrame::None,
        string $title = '',
        string $footer = '',
    ) {
        foreach (self::COLOR_KEYS as $key) {
            if (! is_string($colors[$key] ?? null) || ! preg_match(Color::HEX_PATTERN, $colors[$key])) {
                throw new InvalidArgumentException("Invalid QR color: {$key}.");
            }
        }

        foreach ([$title, $footer] as $text) {
            if (mb_strlen($text) > self::MAX_FRAME_TEXT || preg_match(self::FORBIDDEN_TEXT, $text)) {
                throw new InvalidArgumentException('Invalid QR frame text.');
            }
        }

        // Text only exists on a frame.
        $this->title = $frame === QrFrame::None ? '' : $title;
        $this->footer = $frame === QrFrame::None ? '' : $footer;
    }

    public function hasCustomColors(): bool
    {
        return array_map('strtolower', $this->colors) != $this->template->colors();
    }

    /**
     * Module colors a scanner may miss: too close to the background, or lighter than it (inverted codes fail on many readers).
     *
     * @return list<'dark'|'eye'>
     */
    public function lowContrastColors(): array
    {
        $bg = $this->colors['bg'];

        return array_values(array_filter(['dark', 'eye'], fn (string $key) => Color::luminance($this->colors[$key]) >= Color::luminance($bg)
            || Color::contrast($this->colors[$key], $bg) < self::MIN_SCAN_CONTRAST));
    }
}
