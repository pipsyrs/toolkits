@props(['operation', 'items' => [], 'human'])

{{-- Hasil yang sudah selesai: perbandingan ukuran + pratinjau beresolusi rendah ber-watermark.
     File asli hanya lewat tombol "Unduh hasil". --}}
@php
    $before = $operation->inputSize();
    $after = (int) $operation->output_size;
    $saved = $before > 0 ? (int) round((1 - $after / $before) * 100) : 0;
    $isCompress = in_array($operation->tool, ['pdf-compress', 'image-compress'], true);
    $strongest = in_array($operation->options['level'] ?? null, ['strong', 'high'], true);
@endphp

<div {{ $attributes->class('space-y-4') }}>
    @if($before > 0)
        <dl class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-emerald-900 dark:text-emerald-100">
            <div><dt class="sr-only">Ukuran awal</dt><dd class="tabular-nums">{{ $human($before) }}</dd></div>
            <span aria-hidden="true">→</span>
            <div><dt class="sr-only">Ukuran hasil</dt><dd class="font-semibold tabular-nums">{{ $human($after) }}</dd></div>
            @if($saved > 0)
                <div><dt class="sr-only">Penghematan</dt><dd class="badge bg-emerald-600 text-white dark:bg-emerald-400 dark:text-ink">{{ $saved }}% lebih kecil</dd></div>
            @endif
        </dl>
        @if($isCompress && $saved <= 0)
            <p class="text-sm text-emerald-800 dark:text-emerald-200">
                {{ $strongest
                    ? 'File ini sudah optimal, ukurannya tidak bisa diperkecil lagi. File asli dikembalikan tanpa perubahan.'
                    : 'Ukurannya tidak bisa diperkecil dengan tingkat ini, jadi file asli dikembalikan. Coba tingkat kompresi yang lebih kuat.' }}
            </p>
        @endif
    @endif

    @if($items !== [])
        <div class="space-y-3 rounded-lg border border-emerald-200 bg-surface p-3 dark:border-emerald-500/30" data-no-save>
            <p class="text-xs font-medium text-muted">Pratinjau resolusi rendah. File asli tersedia lewat tombol Unduh hasil.</p>
            <ul class="grid grid-cols-[repeat(auto-fill,minmax(6rem,1fr))] gap-3" aria-label="Pratinjau hasil">
                @foreach($items as $item)
                    <li>
                        <img src="{{ route('files.preview', [$operation, $item['index']]) }}" alt="Pratinjau {{ $item['label'] }}"
                            loading="lazy" decoding="async" width="120" height="160"
                            class="aspect-[3/4] w-full rounded-md border border-line bg-white object-contain">
                        <p class="mt-1 truncate text-center text-xs text-muted" title="{{ $item['label'] }}">{{ $item['label'] }}</p>
                    </li>
                @endforeach
            </ul>
            @if(count($items) === \App\Services\Files\ResultPreview::MAX_ITEMS)
                <p class="text-xs text-muted">Menampilkan {{ count($items) }} pratinjau pertama.</p>
            @endif
        </div>
    @endif
</div>
