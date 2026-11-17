@php
    use App\Services\Tier\Entitlements;

    $limits = $this->limits;
@endphp

<x-tool-layout slug="image-resize" :steps="[
    ['Unggah gambar', 'JPG, PNG, atau WebP, bisa beberapa sekaligus.'],
    ['Atur ukuran', 'Pakai persen, atau isi lebar dan tinggi dalam piksel.'],
    ['Unduh', 'Format asli dipertahankan.'],
]">
    <x-slot:meta><x-file-tool-meta :limits="$limits" /></x-slot:meta>

    <x-file-dropzone :accept="$this->accept ?: 'image/jpeg,image/png,image/webp'" :max-files="Entitlements::uploadLimit($limits, 'max_files_per_op')" :max-mb="Entitlements::uploadLimit($limits, 'max_file_mb')" multiple />
    <x-image-live-preview tool="resize" />

    <x-slot:aside>
        <div class="tool-panel">
            <fieldset class="tool-panel-section">
                <legend class="tool-panel-title mb-3">Metode</legend>
                <div class="grid grid-cols-2 gap-2">
                    <label class="option-tile justify-center">
                        <input type="radio" wire:model.live="mode" value="percent" class="sr-only"> Persen
                    </label>
                    <label class="option-tile justify-center">
                        <input type="radio" wire:model.live="mode" value="dimension" class="sr-only"> Dimensi (px)
                    </label>
                </div>
            </fieldset>

            <div class="tool-panel-section">
                @if($mode === 'percent')
                    <label for="percent" class="tool-panel-title">
                        <span>Skala</span>
                        <span class="font-display text-base font-bold normal-case tracking-normal text-fg tabular-nums" x-text="`${$wire.percent}%`"></span>
                    </label>
                    <input id="percent" type="range" min="1" max="100" step="1" wire:model="percent" class="w-full accent-tool-deep">
                    @error('percent') <p class="field-error">{{ $message }}</p> @enderror
                @else
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="width" class="label">Lebar (px)</label>
                            <input id="width" type="number" min="1" max="10000" wire:model="width" class="input" placeholder="1280">
                            @error('width') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="height" class="label">Tinggi (px)</label>
                            <input id="height" type="number" min="1" max="10000" wire:model="height" class="input" placeholder="720">
                            @error('height') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-muted">
                        <input type="checkbox" wire:model="keepAspect" class="rounded border-line text-ok focus:ring-fg">
                        Pertahankan rasio aspek
                    </label>
                @endif
            </div>

            @include('livewire.tools._process', ['isGuest' => $this->isGuest])
        </div>
    </x-slot:aside>
</x-tool-layout>
