<?php

namespace App\Services;

use App\Services\Qr\FrameLayout;
use App\Services\Qr\QrStyle;
use App\Support\Color;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use GdImage;
use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;

class QrCodeService
{
    public const ECC_LEVELS = ['L', 'M', 'Q', 'H'];

    // GD fills are not antialiased: frame shapes are drawn at this factor and scaled down.
    private const FRAME_SUPERSAMPLE = 2;

    // Finder and alignment patterns stay square in dot templates so scanners still lock on.
    private const SQUARE_MODULES = [QRMatrix::M_FINDER, QRMatrix::M_FINDER_DOT, QRMatrix::M_ALIGNMENT];

    // PNG as data URI; $size is the target QR width in px (approximate), a frame adds to it.
    public function png(string $text, int $size, string $ecc, QrStyle $style): string
    {
        return $this->renderPng($this->matrix($text, $ecc), $size, $style);
    }

    // Same size, density, style and finder patterns as the real code, but the data modules are
    // shuffled (seeded by the input, so it does not flicker), so a screenshot cannot be scanned.
    public function previewPng(string $text, int $size, string $ecc, QrStyle $style): string
    {
        $matrix = $this->matrix($text, $ecc);
        $random = new Randomizer(new Mt19937(crc32($ecc.$text)));

        for ($y = 0; $y < $matrix->getSize(); $y++) {
            for ($x = 0; $x < $matrix->getSize(); $x++) {
                if ($matrix->checkType($x, $y, QRMatrix::M_DATA)) {
                    $matrix->set($x, $y, $random->getInt(0, 1) === 1, QRMatrix::M_DATA);
                }
            }
        }

        return $this->renderPng($matrix, $size, $style);
    }

    // Raw SVG markup; frame text is the only user input in it and is XML-escaped.
    public function svg(string $text, int $size, string $ecc, QrStyle $style): string
    {
        $qr = (string) (new QRMarkupSVG(new QROptions([
            ...$this->styleOptions($style, svg: true),
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'connectPaths' => true,
        ]), $this->matrix($text, $ecc)))->dump();

        return $this->frameSvg(FrameLayout::for($style, $size), $qr, $style->colors['bg']);
    }

    public function svgDataUri(string $svg): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function matrix(string $text, string $ecc): QRMatrix
    {
        if ($text === '' || ! in_array($ecc, self::ECC_LEVELS, true)) {
            throw new InvalidArgumentException('Invalid QR input.');
        }

        return (new QRCode(new QROptions(['eccLevel' => $ecc, 'quietzoneSize' => 2])))
            ->addByteSegment($text)
            ->getQRMatrix();
    }

    private function renderPng(QRMatrix $matrix, int $size, QrStyle $style): string
    {
        $qr = (new QRGdImagePNG(new QROptions([
            ...$this->styleOptions($style, svg: false),
            'bgColor' => Color::rgb($style->colors['bg']),
            'scale' => max(1, intdiv($size, max(1, $matrix->getSize()))),
            'returnResource' => true,
        ]), $matrix))->dump();

        $layout = FrameLayout::for($style, imagesx($qr));
        $image = $layout->isBare() ? $qr : $this->framePng($layout, $qr);

        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    /**
     * @return array<string, mixed>
     */
    private function styleOptions(QrStyle $style, bool $svg): array
    {
        $color = fn (string $hex) => $svg ? $hex : Color::rgb($hex);
        $values = [];

        foreach (QROutputInterface::DEFAULT_MODULE_VALUES as $type => $isDark) {
            if ($isDark) {
                $values[$type] = $color($style->colors['dark']);
            }
        }

        $values[QRMatrix::M_FINDER_DARK] = $values[QRMatrix::M_FINDER_DOT] = $color($style->colors['eye']);

        return [
            'moduleValues' => $values,
            'drawLightModules' => false,
            'drawCircularModules' => $style->template->rounded(),
            'keepAsSquare' => self::SQUARE_MODULES,
        ];
    }

    private function framePng(FrameLayout $layout, GdImage $qr): GdImage
    {
        $k = self::FRAME_SUPERSAMPLE;
        $shapes = $this->transparentCanvas($layout->width * $k, $layout->height * $k);

        foreach ($layout->rects as $rect) {
            $this->fillRoundedRect($shapes, $rect, $k);
        }

        $image = $this->transparentCanvas($layout->width, $layout->height);
        imagealphablending($image, false);
        imagecopyresampled($image, $shapes, 0, 0, 0, 0, $layout->width, $layout->height, $layout->width * $k, $layout->height * $k);
        imagealphablending($image, true);
        imagecopy($image, $qr, $layout->qrX, $layout->qrY, 0, 0, $layout->qrSide, $layout->qrSide);

        foreach ($layout->texts as $text) {
            imagettftext($image, $text['pt'], 0, $text['x'], $text['baseline'], imagecolorallocate($image, ...Color::rgb($text['color'])), FrameLayout::font(), $text['text']);
        }

        return $image;
    }

    private function transparentCanvas(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagealphablending($image, true);

        return $image;
    }

    /**
     * @param  array{x: int, y: int, w: int, h: int, r: int, fill: string}  $rect
     */
    private function fillRoundedRect(GdImage $image, array $rect, int $k): void
    {
        [$x, $y, $w, $h] = [$rect['x'] * $k, $rect['y'] * $k, $rect['w'] * $k, $rect['h'] * $k];
        $r = min($rect['r'] * $k, intdiv(min($w, $h), 2));
        $color = imagecolorallocate($image, ...Color::rgb($rect['fill']));
        [$right, $bottom] = [$x + $w - 1, $y + $h - 1];

        if ($r === 0) {
            imagefilledrectangle($image, $x, $y, $right, $bottom, $color);

            return;
        }

        imagefilledrectangle($image, $x + $r, $y, $right - $r, $bottom, $color);
        imagefilledrectangle($image, $x, $y + $r, $right, $bottom - $r, $color);

        foreach ([[$x + $r, $y + $r], [$right - $r, $y + $r], [$x + $r, $bottom - $r], [$right - $r, $bottom - $r]] as [$cx, $cy]) {
            imagefilledellipse($image, $cx, $cy, 2 * $r, 2 * $r, $color);
        }
    }

    private function frameSvg(FrameLayout $layout, string $qrSvg, string $bg): string
    {
        $parts = [];

        foreach ($layout->rects as $rect) {
            $parts[] = sprintf('<rect x="%d" y="%d" width="%d" height="%d" rx="%d" fill="%s"/>', $rect['x'], $rect['y'], $rect['w'], $rect['h'], $rect['r'], $rect['fill']);
        }

        // The library draws no background, so the QR area gets one of its own.
        $parts[] = sprintf('<rect x="%d" y="%d" width="%3$d" height="%3$d" fill="%4$s"/>', $layout->qrX, $layout->qrY, $layout->qrSide, $bg);
        $parts[] = preg_replace('/^<svg /', sprintf('<svg x="%d" y="%d" width="%3$d" height="%3$d" ', $layout->qrX, $layout->qrY, $layout->qrSide), $qrSvg, 1) ?? $qrSvg;

        foreach ($layout->texts as $text) {
            // textLength pins the measured width, so a viewer without Inter still fits the line.
            $parts[] = sprintf(
                '<text x="%d" y="%d" text-anchor="middle" font-family="Inter, Arial, sans-serif" font-weight="600" font-size="%.2F" textLength="%d" lengthAdjust="spacingAndGlyphs" fill="%s">%s</text>',
                $text['cx'], $text['baseline'], $text['px'], $text['width'], $text['color'],
                htmlspecialchars($text['text'], ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            );
        }

        return sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d">%3$s</svg>', $layout->width, $layout->height, implode('', $parts));
    }
}
