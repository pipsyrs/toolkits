@php
    use App\Services\Tier\Entitlements;

    $limits = $this->limits;
    $formats = [
        'webp' => ['WebP', 'Ukuran kecil'],
        'jpg' => ['JPG', 'Paling kompatibel'],
        'png' => ['PNG', 'Tanpa rugi, transparan'],
    ];
@endphp

<x-tool-layout slug="image-convert" :steps="[
    ['Unggah gambar', 'JPG, PNG, atau WebP, bisa beberapa sekaligus.'],
    ['Pilih format', 'Pratinjau dan ukuran hasil muncul langsung.'],
    ['Unduh', 'Beberapa file dikemas jadi satu ZIP.'],
]">
    <x-slot:meta><x-file-tool-meta :limits="$limits" /></x-slot:meta>

    <x-file-dropzone :accept="$this->accept ?: 'image/jpeg,image/png,image/webp'" :max-files="Entitlements::uploadLimit($limits, 'max_files_per_op')" :max-mb="Entitlements::uploadLimit($limits, 'max_file_mb')" multiple />
    <x-image-live-preview tool="convert" />

    <x-slot:aside>
        <div class="tool-panel">
            <fieldset class="tool-panel-section">
                <legend class="tool-panel-title mb-3">Konversi ke</legend>
                <div class="grid gap-2">
                    @foreach($formats as $val => [$label, $hint])
                        <label class="option-tile justify-between">
                            <input type="radio" wire:model="targetFormat" value="{{ $val }}" class="sr-only">
                            <span class="font-medium">{{ $label }}</span>
                            <span class="option-hint">{{ $hint }}</span>
                        </label>
                    @endforeach
                </div>
                @error('targetFormat') <p class="field-error">{{ $message }}</p> @enderror
            </fieldset>

            @include('livewire.tools._process', ['isGuest' => $this->isGuest])
        </div>
    </x-slot:aside>
</x-tool-layout>
