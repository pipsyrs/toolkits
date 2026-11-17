@php
    use App\Services\Tier\Entitlements;

    $limits = $this->limits;
    $groups = [
        'pageSize' => ['Ukuran halaman', ['a4' => 'A4', 'letter' => 'Letter', 'fit' => 'Sesuai gambar']],
        'orientation' => ['Orientasi', ['portrait' => 'Potret', 'landscape' => 'Lanskap']],
        'margin' => ['Margin', ['0' => 'Tanpa', '10' => 'Kecil', '20' => 'Sedang']],
    ];
@endphp

<x-tool-layout slug="image-to-pdf" :steps="[
    ['Unggah gambar', 'Satu atau beberapa gambar JPG/PNG.'],
    ['Atur halaman', 'Urutkan dengan tombol naik/turun, pilih ukuran dan margin.'],
    ['Unduh PDF', 'Semua gambar jadi satu file PDF.'],
]">
    <x-slot:meta><x-file-tool-meta :limits="$limits" /></x-slot:meta>

    <x-file-dropzone :accept="$this->accept ?: 'image/jpeg,image/png'" :max-files="Entitlements::uploadLimit($limits, 'max_files_per_op')" :max-mb="Entitlements::uploadLimit($limits, 'max_file_mb')" multiple sortable />

    <x-preview-panel title="Pratinjau halaman" hint="Mengikuti ukuran, orientasi, dan margin"
        x-data="imagePdfLayout" x-on:files-changed.window="setFiles($event.detail.files)" x-show="items.length">
        <ol class="grid grid-cols-[repeat(auto-fill,minmax(7rem,1fr))] items-start gap-4" aria-label="Halaman PDF yang akan dibuat">
            <template x-for="(item, i) in items" :key="item.key">
                <li>
                    <div class="relative w-full overflow-hidden rounded-sm border border-line bg-white shadow-sm" :style="pageStyle(item)">
                        <img :src="item.url" alt="" class="absolute object-fill" :style="imageStyle(item)">
                    </div>
                    <p class="mt-1 text-center text-xs text-muted" x-text="`Halaman ${i + 1}`"></p>
                </li>
            </template>
        </ol>
    </x-preview-panel>

    <x-slot:aside>
        <div class="tool-panel">
            @foreach($groups as $property => [$legend, $options])
                <fieldset class="tool-panel-section">
                    <legend class="tool-panel-title mb-3">{{ $legend }}</legend>
                    <div @class(['grid gap-2', 'grid-cols-2' => count($options) === 2, 'grid-cols-3' => count($options) === 3])>
                        @foreach($options as $val => $label)
                            <label class="option-tile justify-center text-center">
                                <input type="radio" wire:model="{{ $property }}" value="{{ $val }}" class="sr-only"> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error($property) <p class="field-error">{{ $message }}</p> @enderror
                </fieldset>
            @endforeach

            @include('livewire.tools._process', ['isGuest' => $this->isGuest])
        </div>
    </x-slot:aside>
</x-tool-layout>
