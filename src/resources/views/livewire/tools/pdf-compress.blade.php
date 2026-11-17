@php
    use App\Services\Tier\Entitlements;

    $limits = $this->limits;
    $levels = [
        'light' => ['Ringan', '200 dpi, cocok untuk dicetak'],
        'recommended' => ['Disarankan', '150 dpi, untuk layar dan email'],
        'strong' => ['Kuat', '96 dpi, paling kecil'],
    ];
@endphp

<x-tool-layout slug="pdf-compress" :steps="[
    ['Unggah PDF', 'Satu atau beberapa file PDF.'],
    ['Pilih tingkat', 'Makin kuat, makin kecil, gambar bisa tampak buram.'],
    ['Unduh', 'Ukuran sebelum dan sesudah ditampilkan.'],
]">
    <x-slot:meta><x-file-tool-meta :limits="$limits" /></x-slot:meta>

    <x-file-dropzone :accept="$this->accept ?: 'application/pdf'" :max-files="Entitlements::uploadLimit($limits, 'max_files_per_op')" :max-mb="Entitlements::uploadLimit($limits, 'max_file_mb')" />

    <x-preview-panel title="Pratinjau file" x-data="pdfPages({ limit: 8, width: 110 })"
        x-on:files-changed.window="setFiles($event.detail.files)" x-show="loading || error || pages.length">
        <p x-show="total" class="text-sm text-muted" x-text="`${total} halaman. Ukuran hasil terlihat setelah diproses, beserta pratinjaunya.`"></p>
        <x-pdf-pages label="Halaman PDF" />
    </x-preview-panel>

    <x-slot:aside>
        <div class="tool-panel">
            <fieldset class="tool-panel-section">
                <legend class="tool-panel-title mb-3">Tingkat kompresi</legend>
                <div class="grid gap-2">
                    @foreach($levels as $val => [$label, $hint])
                        <label class="option-tile flex-col items-start gap-0.5">
                            <input type="radio" wire:model="level" value="{{ $val }}" class="sr-only">
                            <span class="font-medium">{{ $label }}</span>
                            <span class="option-hint">{{ $hint }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            @include('livewire.tools._process', ['isGuest' => $this->isGuest])
        </div>
    </x-slot:aside>
</x-tool-layout>
