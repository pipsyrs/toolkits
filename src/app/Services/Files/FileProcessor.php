<?php

namespace App\Services\Files;

use App\Exceptions\FileProcessingException;
use App\Models\FileOperation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

// Runs one file operation; several results are delivered as a single ZIP.
class FileProcessor
{
    public function __construct(
        protected ImageProcessor $images,
        protected PdfProcessor $pdf,
        protected JpegPdfWriter $pdfWriter,
        protected ResultPreview $previews,
    ) {}

    /**
     * @return array{path: string, name: string, size: int} path relative to the files disk
     */
    public function run(FileOperation $operation): array
    {
        $disk = Storage::disk(config('toolkits.files.disk'));
        $outDir = $disk->path($operation->directory().'/out');
        File::ensureDirectoryExists($outDir);

        $inputs = array_map(fn (array $input) => [
            'path' => $disk->path($input['path']),
            'base' => Str::slug(pathinfo((string) $input['name'], PATHINFO_FILENAME)) ?: 'file',
        ], $operation->inputs);

        $outputs = $this->dispatch($operation->tool, $inputs, $operation->options ?? [], $outDir);

        if ($outputs === []) {
            throw new FileProcessingException('Tidak ada hasil yang dibuat.');
        }

        $this->buildPreview($operation, $outputs);

        $name = count($outputs) === 1 ? basename($outputs[0]) : 'toolkits-'.$operation->tool.'.zip';
        $relative = $operation->directory().'/'.$name;

        count($outputs) === 1
            ? File::move($outputs[0], $disk->path($relative))
            : $this->zip($outputs, $disk->path($relative));

        File::deleteDirectory($outDir);

        return ['path' => $relative, 'name' => $name, 'size' => (int) $disk->size($relative)];
    }

    /**
     * Optional extra: a broken preview must not fail a finished result.
     *
     * @param  list<string>  $outputs
     */
    private function buildPreview(FileOperation $operation, array $outputs): void
    {
        try {
            $this->previews->build($operation, $outputs);
        } catch (Throwable $e) {
            Log::warning('Result preview failed', ['operation' => $operation->getKey(), 'tool' => $operation->tool, 'exception' => $e]);
        }
    }

    /**
     * @param  list<array{path: string, base: string}>  $inputs
     * @param  array<string, mixed>  $options
     * @return list<string> absolute output paths
     */
    private function dispatch(string $tool, array $inputs, array $options, string $outDir): array
    {
        $first = $inputs[0];

        return match ($tool) {
            'image-convert' => $this->each($inputs, $outDir, fn ($in) => $in['base'].'.'.$options['targetFormat'],
                fn ($in, $out) => $this->images->convert($in['path'], $out, (string) $options['targetFormat'])),
            'image-compress' => $this->each($inputs, $outDir, fn ($in) => $in['base'].'-kompres.'.$this->images->formatOf($in['path']),
                fn ($in, $out) => $this->images->compress($in['path'], $out, (string) $options['level'])),
            'image-resize' => $this->each($inputs, $outDir, fn ($in) => $in['base'].'-resize.'.$this->images->formatOf($in['path']),
                fn ($in, $out) => $this->images->resize($in['path'], $out, $options)),
            'remove-bg' => $this->each($inputs, $outDir, fn ($in) => $in['base'].'-tanpa-bg.png',
                fn ($in, $out) => $this->images->removeBackground($in['path'], $out, $options)),
            'pdf-compress' => $this->each($inputs, $outDir, fn ($in) => $in['base'].'-kompres.pdf',
                fn ($in, $out) => $this->pdf->compress($in['path'], $out, (string) $options['level'])),
            'pdf-merge' => $this->single($outDir.'/gabungan.pdf',
                fn ($out) => $this->pdf->merge(array_column($inputs, 'path'), $out)),
            'pdf-split' => ($options['mode'] ?? 'each') === 'extract'
                ? $this->single($outDir.'/'.$first['base'].'-halaman.pdf', fn ($out) => $this->pdf->extract($first['path'], (string) $options['ranges'], $out))
                : $this->pdf->splitEach($first['path'], $outDir),
            'image-to-pdf' => $this->single($outDir.'/gambar.pdf', fn ($out) => File::put($out, $this->pdfWriter->write(
                array_map(fn ($in) => $this->images->toJpeg($in['path']), $inputs),
                (string) $options['pageSize'], (string) $options['orientation'], (int) $options['margin'],
            ))),
            default => throw new FileProcessingException('Alat tidak dikenal.'),
        };
    }

    /**
     * @return list<string>
     */
    private function each(array $inputs, string $outDir, callable $name, callable $process): array
    {
        $outputs = [];

        foreach ($inputs as $input) {
            $out = $this->uniquePath($outDir, $name($input));
            $process($input, $out);
            $outputs[] = $out;
        }

        return $outputs;
    }

    /**
     * @return list<string>
     */
    private function single(string $out, callable $process): array
    {
        $process($out);

        return [$out];
    }

    // Two uploads with the same name must not overwrite each other.
    private function uniquePath(string $dir, string $name): string
    {
        $path = $dir.'/'.$name;
        $i = 2;

        while (file_exists($path)) {
            $path = $dir.'/'.pathinfo($name, PATHINFO_FILENAME).'-'.$i++.'.'.pathinfo($name, PATHINFO_EXTENSION);
        }

        return $path;
    }

    /**
     * @param  list<string>  $files
     */
    private function zip(array $files, string $target): void
    {
        $zip = new ZipArchive;

        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new FileProcessingException('Gagal membuat file ZIP.');
        }

        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }

        $zip->close();
    }
}
