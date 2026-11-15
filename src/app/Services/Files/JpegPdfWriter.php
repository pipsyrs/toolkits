<?php

namespace App\Services\Files;

// Minimal PDF 1.4 writer: one JPEG (DCTDecode) per page, no fonts or text.
class JpegPdfWriter
{
    private const PT_PER_MM = 72 / 25.4;

    // Pixel to point when the page follows the image size (96 dpi).
    private const PT_PER_PX = 72 / 96;

    private const PAGE_SIZES_MM = [
        'a4' => [210, 297],
        'letter' => [215.9, 279.4],
    ];

    /**
     * @param  list<array{data: string, width: int, height: int}>  $images
     */
    public function write(array $images, string $pageSize, string $orientation, int $marginMm): string
    {
        $objects = [];
        $pageIds = [];
        $nextId = 3; // 1 = catalog, 2 = page tree

        foreach ($images as $image) {
            [$pageW, $pageH] = $this->pageSize($image, $pageSize, $orientation, $marginMm);
            $margin = $marginMm * self::PT_PER_MM;
            [$drawW, $drawH, $x, $y] = $this->fit($image['width'], $image['height'], $pageW, $pageH, $margin);

            $imageId = $nextId++;
            $contentId = $nextId++;
            $pageId = $nextId++;
            $pageIds[] = $pageId;

            $objects[$imageId] = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $image['width'], $image['height'], strlen($image['data']), $image['data'],
            );

            $content = sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /Im0 Do Q', $drawW, $drawH, $x, $y);
            $objects[$contentId] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($content), $content);

            $objects[$pageId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /XObject << /Im0 %d 0 R >> >> /Contents %d 0 R >>',
                $pageW, $pageH, $imageId, $contentId,
            );
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', array_map(fn ($id) => "$id 0 R", $pageIds)), count($pageIds));
        ksort($objects);

        return $this->serialize($objects);
    }

    /**
     * @param  array<int, string>  $objects
     */
    private function serialize(array $objects): string
    {
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$body\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= sprintf("xref\n0 %d\n0000000000 65535 f \n", count($objects) + 1);

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.sprintf("trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n", count($objects) + 1, $xref);
    }

    /**
     * @param  array{width: int, height: int}  $image
     * @return array{float, float}
     */
    private function pageSize(array $image, string $pageSize, string $orientation, int $marginMm): array
    {
        if (! isset(self::PAGE_SIZES_MM[$pageSize])) {
            $margin = 2 * $marginMm * self::PT_PER_MM;

            return [$image['width'] * self::PT_PER_PX + $margin, $image['height'] * self::PT_PER_PX + $margin];
        }

        [$w, $h] = array_map(fn ($mm) => $mm * self::PT_PER_MM, self::PAGE_SIZES_MM[$pageSize]);

        return $orientation === 'landscape' ? [$h, $w] : [$w, $h];
    }

    /**
     * Scale down to the printable area and centre.
     *
     * @return array{float, float, float, float}
     */
    private function fit(int $imgW, int $imgH, float $pageW, float $pageH, float $margin): array
    {
        $areaW = $pageW - 2 * $margin;
        $areaH = $pageH - 2 * $margin;
        $scale = min($areaW / ($imgW * self::PT_PER_PX), $areaH / ($imgH * self::PT_PER_PX), 1);
        $drawW = $imgW * self::PT_PER_PX * $scale;
        $drawH = $imgH * self::PT_PER_PX * $scale;

        return [$drawW, $drawH, ($pageW - $drawW) / 2, ($pageH - $drawH) / 2];
    }
}
