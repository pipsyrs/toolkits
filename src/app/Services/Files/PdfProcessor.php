<?php

namespace App\Services\Files;

use App\Exceptions\FileProcessingException;
use Illuminate\Support\Facades\Process;

// PDF operations through Ghostscript (pdfwrite, jpeg for previews), run with -dSAFER and a timeout.
class PdfProcessor
{
    private const PDFWRITE = ['-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.5'];

    // Image dpi and JPEG QFactor (higher = smaller, blurrier) per level. The bare /printer and /ebook
    // presets only downsample images above 1.5x their dpi and pass JPEGs through unchanged, so most
    // PDFs came out the same size.
    private const COMPRESS_LEVELS = [
        'light' => ['dpi' => 200, 'qfactor' => 0.4],
        'recommended' => ['dpi' => 150, 'qfactor' => 0.76],
        'strong' => ['dpi' => 96, 'qfactor' => 1.3],
    ];

    /**
     * @param  list<string>  $inputs
     */
    public function merge(array $inputs, string $out): void
    {
        $this->ghostscript([...self::PDFWRITE, "-sOutputFile={$out}", ...$this->checked($inputs)]);
    }

    // $ranges is validated upstream as "1-3,5".
    public function extract(string $src, string $ranges, string $out): void
    {
        $this->ghostscript([...self::PDFWRITE, '-dPDFSTOPONERROR', "-sPageList={$ranges}", "-sOutputFile={$out}", ...$this->checked([$src])],
            'Halaman yang dipilih tidak ada di PDF.');
    }

    /**
     * @return list<string> one file per page
     */
    public function splitEach(string $src, string $outDir): array
    {
        $this->ghostscript([...self::PDFWRITE, "-sOutputFile={$outDir}/halaman-%03d.pdf", ...$this->checked([$src])]);

        $pages = glob($outDir.'/halaman-*.pdf') ?: [];
        sort($pages);

        return $pages;
    }

    /**
     * First $maxPages pages as JPEG, for previews.
     *
     * @return list<string>
     */
    public function renderPages(string $src, string $outDir, int $maxPages, int $dpi): array
    {
        $this->ghostscript([
            '-sDEVICE=jpeg', '-dJPEGQ=85', "-r{$dpi}", '-dTextAlphaBits=4', '-dGraphicsAlphaBits=4',
            '-dFirstPage=1', "-dLastPage={$maxPages}", "-sOutputFile={$outDir}/page-%03d.jpg", ...$this->checked([$src]),
        ]);

        $pages = glob($outDir.'/page-*.jpg') ?: [];
        sort($pages);

        return $pages;
    }

    public function compress(string $src, string $out, string $level): void
    {
        ['dpi' => $dpi, 'qfactor' => $qfactor] = self::COMPRESS_LEVELS[$level] ?? self::COMPRESS_LEVELS['recommended'];
        $jpeg = sprintf('<< /QFactor %.2F /Blend 1 /HSamples [2 1 1 2] /VSamples [2 1 1 2] >>', $qfactor);

        $this->ghostscript([
            ...self::PDFWRITE,
            '-dPDFSETTINGS=/ebook',
            '-dPassThroughJPEGImages=false', '-dPassThroughJPXImages=false',
            '-dDetectDuplicateImages=true', '-dSubsetFonts=true', '-dCompressFonts=true',
            ...$this->downsample('Color', $dpi), ...$this->downsample('Gray', $dpi), ...$this->downsample('Mono', $dpi * 2),
            "-sOutputFile={$out}",
            '-c', "<< /ColorACSImageDict {$jpeg} /GrayACSImageDict {$jpeg} >> setdistillerparams",
            '-f', ...$this->checked([$src]),
        ]);

        // Already optimised files can grow; never hand back a bigger file.
        if (filesize($out) >= filesize($src)) {
            copy($src, $out);
        }
    }

    /**
     * Threshold 1.0: downsample every image above the target dpi, not only those above 1.5x.
     *
     * @return list<string>
     */
    private function downsample(string $kind, int $dpi): array
    {
        return ["-dDownsample{$kind}Images=true", "-d{$kind}ImageResolution={$dpi}", "-d{$kind}ImageDownsampleThreshold=1.0"];
    }

    /**
     * @param  list<string>  $args
     */
    private function ghostscript(array $args, string $failure = 'PDF rusak, terkunci sandi, atau tidak dapat diproses.'): void
    {
        $result = Process::timeout((int) config('toolkits.files.process_timeout'))->run([
            config('toolkits.files.ghostscript'),
            '-dSAFER', '-dBATCH', '-dNOPAUSE', '-dQUIET',
            ...$args,
        ]);

        if ($result->failed()) {
            throw new FileProcessingException($failure);
        }
    }

    /**
     * Ghostscript runs anything that is not a PDF as PostScript; only pass real PDFs.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function checked(array $paths): array
    {
        foreach ($paths as $path) {
            $handle = fopen($path, 'rb');
            $magic = $handle ? fread($handle, 5) : '';
            $handle && fclose($handle);

            if ($magic !== '%PDF-') {
                throw new FileProcessingException('File bukan PDF yang valid.');
            }
        }

        return $paths;
    }
}
