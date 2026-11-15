<?php

namespace App\Services\Files;

use App\Models\FileOperation;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Low-res, watermarked JPEG previews of a result. The real file is only served by the download
// route, so a saved or screenshotted preview is never a usable copy.
class ResultPreview
{
    public const MAX_ITEMS = 12;

    private const MAX_SIDE = 480;

    private const PDF_DPI = 50;

    private const DIR = 'preview';

    private const INDEX = 'index.json';

    public function __construct(
        protected ImageProcessor $images,
        protected PdfProcessor $pdf,
    ) {}

    /**
     * One page per item for a single PDF, otherwise one item per output file.
     *
     * @param  list<string>  $outputs  absolute paths, before they are moved or zipped
     */
    public function build(FileOperation $operation, array $outputs): void
    {
        $dir = $this->disk()->path($this->dir($operation));
        $work = $dir.'/render';
        File::ensureDirectoryExists($work);

        try {
            $items = [];

            foreach (array_slice($this->sources($outputs, $work), 0, self::MAX_ITEMS) as $i => [$path, $label]) {
                $this->images->preview($path, $dir.'/'.$this->fileName($i), self::MAX_SIDE);
                $items[] = $label;
            }

            File::put($dir.'/'.self::INDEX, (string) json_encode($items));
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * @return list<array{index: int, label: string}>
     */
    public function items(FileOperation $operation): array
    {
        $index = $this->dir($operation).'/'.self::INDEX;

        if (! $operation->isDownloadable() || ! $this->disk()->exists($index)) {
            return [];
        }

        $items = [];

        foreach (array_values((array) json_decode((string) $this->disk()->get($index), true)) as $i => $label) {
            $items[] = ['index' => $i, 'label' => (string) $label];
        }

        return $items;
    }

    public function response(FileOperation $operation, int $index): ?StreamedResponse
    {
        if ($index < 0 || $index >= count($this->items($operation))) {
            return null;
        }

        return $this->disk()->response($this->dir($operation).'/'.$this->fileName($index), null, [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @param  list<string>  $outputs
     * @return list<array{string, string}> image path and label
     */
    private function sources(array $outputs, string $work): array
    {
        if (count($outputs) === 1 && $this->isPdf($outputs[0])) {
            $pages = $this->pdf->renderPages($outputs[0], $work, self::MAX_ITEMS, self::PDF_DPI);

            return array_map(fn ($page, $i) => [$page, (string) ($i + 1)], $pages, array_keys($pages));
        }

        $sources = [];

        foreach (array_slice($outputs, 0, self::MAX_ITEMS) as $i => $output) {
            $image = $output;

            if ($this->isPdf($output)) {
                File::ensureDirectoryExists($work.'/'.$i);
                $image = $this->pdf->renderPages($output, $work.'/'.$i, 1, self::PDF_DPI)[0] ?? null;
            }

            $image !== null && $sources[] = [$image, basename($output)];
        }

        return $sources;
    }

    private function isPdf(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
    }

    private function fileName(int $index): string
    {
        return sprintf('%02d.jpg', $index + 1);
    }

    private function dir(FileOperation $operation): string
    {
        return $operation->directory().'/'.self::DIR;
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk(config('toolkits.files.disk'));
    }
}
