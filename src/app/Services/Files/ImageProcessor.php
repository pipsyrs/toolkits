<?php

namespace App\Services\Files;

use App\Exceptions\FileProcessingException;
use GdImage;

// GD based image operations; every method writes $out and never touches $src.
class ImageProcessor
{
    public const FORMATS = ['jpg', 'png', 'webp'];

    private const COMPRESS_QUALITY = ['low' => 85, 'medium' => 70, 'high' => 50];

    private const DEFAULT_QUALITY = 90;

    private const PNG_LEVEL = 9;

    private const PREVIEW_QUALITY = 60;

    private const WATERMARK = 'PRATINJAU';

    public function convert(string $src, string $out, string $format): void
    {
        $this->save($this->load($src), $out, $format, self::DEFAULT_QUALITY);
    }

    public function compress(string $src, string $out, string $level): void
    {
        $format = $this->formatOf($src);
        $image = $this->load($src);

        if ($format === 'png' && $level === 'high') {
            // Lossy for PNG: reduce to a 256 colour palette.
            imagetruecolortopalette($image, true, 256);
        }

        $this->save($image, $out, $format, self::COMPRESS_QUALITY[$level] ?? self::COMPRESS_QUALITY['medium']);

        // Never hand back a bigger file than the original.
        if (filesize($out) >= filesize($src)) {
            copy($src, $out);
        }
    }

    /**
     * @param  array{mode: string, percent?: int|null, width?: int|null, height?: int|null, keepAspect?: bool}  $options
     */
    public function resize(string $src, string $out, array $options): void
    {
        $image = $this->load($src);
        [$w, $h] = [imagesx($image), imagesy($image)];
        [$newW, $newH] = $this->targetSize($w, $h, $options);

        if ($newW * $newH > (int) config('toolkits.files.max_pixels')) {
            throw new FileProcessingException('Ukuran hasil terlalu besar.');
        }

        $resized = imagecreatetruecolor($newW, $newH);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $w, $h);

        $this->save($resized, $out, $this->formatOf($src), self::DEFAULT_QUALITY);
    }

    /**
     * JPEG bytes plus pixel size, for embedding in a PDF.
     *
     * @return array{data: string, width: int, height: int}
     */
    public function toJpeg(string $src): array
    {
        $image = $this->flatten($this->load($src));

        ob_start();
        imagejpeg($image, null, self::DEFAULT_QUALITY);

        return ['data' => (string) ob_get_clean(), 'width' => imagesx($image), 'height' => imagesy($image)];
    }

    // Small, watermarked JPEG for on-page previews; never a usable stand-in for the result.
    public function preview(string $src, string $out, int $maxSide): void
    {
        $image = $this->load($src);
        [$w, $h] = [imagesx($image), imagesy($image)];
        $scale = min(1, $maxSide / max($w, $h));
        [$newW, $newH] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];

        $thumb = imagecreatetruecolor($newW, $newH);
        imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newW, $newH, $w, $h);
        $this->watermark($thumb);

        if (! imagejpeg($thumb, $out, self::PREVIEW_QUALITY)) {
            throw new FileProcessingException('Gagal membuat pratinjau.');
        }
    }

    // Re-encoded WebP that fits $maxSide, keeping transparency; re-encoding drops anything hidden in the upload.
    public function logo(string $src, string $out, int $maxSide): void
    {
        $image = $this->load($src);
        [$w, $h] = [imagesx($image), imagesy($image)];
        $scale = min(1, $maxSide / max($w, $h));
        $logo = $this->transparentCanvas(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        imagecopyresampled($logo, $image, 0, 0, 0, 0, imagesx($logo), imagesy($logo), $w, $h);

        $this->save($logo, $out, 'webp', self::DEFAULT_QUALITY);
    }

    public function formatOf(string $path): string
    {
        return match (@getimagesize($path)[2] ?? null) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => throw new FileProcessingException('Format gambar tidak didukung.'),
        };
    }

    private function load(string $path): GdImage
    {
        $info = @getimagesize($path);

        if ($info === false) {
            throw new FileProcessingException('File gambar rusak atau tidak dikenali.');
        }

        if ((int) config('toolkits.files.max_pixels') < $info[0] * $info[1]) {
            throw new FileProcessingException('Resolusi gambar terlalu besar.');
        }

        $image = match ($this->formatOf($path)) {
            'jpg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => @imagecreatefromwebp($path),
        };

        if (! $image instanceof GdImage) {
            throw new FileProcessingException('File gambar rusak atau tidak dikenali.');
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $info[2] === IMAGETYPE_JPEG ? $this->applyExifOrientation($image, $path) : $image;
    }

    private function save(GdImage $image, string $out, string $format, int $quality): void
    {
        $ok = match ($format) {
            'jpg' => imagejpeg($this->flatten($image), $out, $quality),
            'png' => $this->savePng($image, $out),
            'webp' => imagewebp($image, $out, $quality),
            default => throw new FileProcessingException('Format tujuan tidak didukung.'),
        };

        if (! $ok) {
            throw new FileProcessingException('Gagal menyimpan gambar.');
        }
    }

    private function savePng(GdImage $image, string $out): bool
    {
        imagesavealpha($image, true);

        return imagepng($image, $out, self::PNG_LEVEL);
    }

    // Diagonal "PRATINJAU" tiles. The built-in GD font is scaled up, so no font file is needed.
    private function watermark(GdImage $image): void
    {
        $font = 5;
        $w = imagefontwidth($font) * strlen(self::WATERMARK) + 2;
        $h = imagefontheight($font) + 2;
        $stamp = $this->transparentCanvas($w, $h);
        imagestring($stamp, $font, 2, 2, self::WATERMARK, imagecolorallocatealpha($stamp, 0, 0, 0, 85));
        imagestring($stamp, $font, 1, 1, self::WATERMARK, imagecolorallocatealpha($stamp, 255, 255, 255, 45));

        $scale = max(1, imagesx($image) * 0.3 / $w);
        $big = $this->transparentCanvas((int) round($w * $scale), (int) round($h * $scale));
        imagecopyresampled($big, $stamp, 0, 0, 0, 0, imagesx($big), imagesy($big), $w, $h);
        $big = imagerotate($big, 25, imagecolorallocatealpha($big, 0, 0, 0, 127));

        imagealphablending($image, true);
        [$stepX, $stepY] = [imagesx($big), (int) (imagesy($big) * 0.7)];

        for ($row = 0, $y = -$stepY; $y < imagesy($image); $row++, $y += $stepY) {
            for ($x = -$stepX + ($row % 2) * intdiv($stepX, 2); $x < imagesx($image); $x += $stepX) {
                imagecopy($image, $big, $x, $y, 0, 0, imagesx($big), imagesy($big));
            }
        }
    }

    private function transparentCanvas(int $w, int $h): GdImage
    {
        $canvas = imagecreatetruecolor($w, $h);
        imagealphablending($canvas, false);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagesavealpha($canvas, true);

        return $canvas;
    }

    // JPEG has no alpha: paint transparent areas white.
    private function flatten(GdImage $image): GdImage
    {
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $flat;
    }

    private function applyExifOrientation(GdImage $image, string $path): GdImage
    {
        $orientation = function_exists('exif_read_data') ? (int) (@exif_read_data($path)['Orientation'] ?? 1) : 1;

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    /**
     * @return array{int, int}
     */
    private function targetSize(int $w, int $h, array $options): array
    {
        if (($options['mode'] ?? 'percent') === 'percent') {
            $ratio = max(1, min(100, (int) ($options['percent'] ?? 100))) / 100;

            return [max(1, (int) round($w * $ratio)), max(1, (int) round($h * $ratio))];
        }

        $width = isset($options['width']) ? (int) $options['width'] : null;
        $height = isset($options['height']) ? (int) $options['height'] : null;

        if ($options['keepAspect'] ?? true) {
            // Fit inside the given box, keeping proportions.
            $scale = min($width ? $width / $w : INF, $height ? $height / $h : INF);

            return [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];
        }

        return [$width ?: $w, $height ?: $h];
    }
}
