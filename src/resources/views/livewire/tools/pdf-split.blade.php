@php
    use App\Services\Tier\Entitlements;

    $limits = $this->limits;
@endphp

<x-tool-layout slug="pdf-split" :steps="[
    ['Unggah PDF', 'Satu file PDF per proses.'],
    ['Pilih halaman', 'Klik halaman atau ketik rentangnya, mis. 1-3,5.'],
    ['Unduh', 'Satu PDF berisi halaman pilihan, atau ZIP per halaman.'],
]">
    <x-slot:meta><x-file-tool-meta :limits="$limits" /></x-slot:meta>

    <x-file-dropzone :accept="$this->accept ?: 'application/pdf'" :max-files="1" :max-mb="Entitlements::uploadLimit($limits, 'max_file_mb')" />

    <x-preview-panel title="Pilih halaman" x-data="pdfSplit" x-on:files-changed.window="setFiles($event.detail.files)"
        x-show="loading || error || pages.length">
        <p x-show="total" class="text-sm text-muted" aria-live="polite" x-text="summary()"></p>
        <p x-show="extract && selection.outside" class="field-error" x-text="`Ada nomor halaman di luar 1–${total}; halaman itu akan membuat proses gagal.`"></p>
        <x-pdf-pages label="Halaman PDF" />
    </x-preview-panel>

    <x-slot:aside>
        <div class="tool-panel">
            <fieldset class="tool-panel-section">
                <legend class="tool-panel-title mb-3">Mode</legend>
                <div class="grid gap-2">
                    <label class="option-tile justify-between">
                        <input type="radio" wire:model.live="mode" value="extract" class="sr-only">
                        <span class="font-medium">Ambil rentang</span>
                        <span class="option-hint">1 PDF berisi pilihanmu</span>
                    </label>
                    <label class="option-tile justify-between">
                        <input type="radio" wire:model.live="mode" value="each" class="sr-only">
                        <span class="font-medium">Pecah tiap halaman</span>
                        <span class="option-hint">ZIP, 1 file per halaman</span>
                    </label>
                </div>
            </fieldset>

            @if($mode === 'extract')
                <div class="tool-panel-section">
                    <label for="ranges" class="tool-panel-title">Rentang halaman</label>
                    <input id="ranges" type="text" wire:model="ranges" placeholder="1-3,5,8-10"
                        class="input font-mono @error('ranges') border-danger @enderror" inputmode="numeric">
                    <p class="text-xs text-muted">Pisahkan dengan koma. Contoh: <code>1-3,5,8-10</code></p>
                    @error('ranges') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            @endif

            @include('livewire.tools._process', ['isGuest' => $this->isGuest])
        </div>
    </x-slot:aside>
</x-tool-layout>
