@php
    use App\Services\Tier\Entitlements;

    $limits = $this->limits;
    $maxMb = Entitlements::uploadLimit($limits, 'max_file_mb');
    $maxFiles = Entitlements::uploadLimit($limits, 'max_files_per_op');
    $accept = $this->accept ?: 'image/jpeg,image/png,image/webp';
    $isGuest = $this->isGuest;
@endphp

<div class="mx-auto max-w-3xl">
    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Ubah Ukuran Gambar</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Perbesar atau perkecil gambar berdasarkan persen atau dimensi.</p>
    </header>

    <div class="card space-y-5 p-6">
        <x-file-dropzone :accept="$accept" :max-files="$maxFiles" :max-mb="$maxMb" multiple />

        <fieldset>
            <legend class="label">Metode</legend>
            <div class="flex gap-2">
                <label class="flex flex-1 cursor-pointer items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:border-slate-700 dark:has-[:checked]:bg-brand-600/10">
                    <input type="radio" wire:model.live="mode" value="percent" class="sr-only"> Persen
                </label>
                <label class="flex flex-1 cursor-pointer items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:border-slate-700 dark:has-[:checked]:bg-brand-600/10">
                    <input type="radio" wire:model.live="mode" value="dimension" class="sr-only"> Dimensi (px)
                </label>
            </div>
        </fieldset>

        @if(($mode ?? 'percent') === 'percent')
            <div>
                <label for="percent" class="label">Skala <span class="font-normal text-slate-500 dark:text-slate-400">(<span x-text="$wire.percent"></span>%)</span></label>
                <input id="percent" type="range" min="1" max="100" step="1" wire:model="percent" class="w-full accent-brand-600">
                @error('percent') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
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
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input type="checkbox" wire:model="keepAspect" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800">
                Pertahankan rasio aspek
            </label>
        @endif

        <x-image-live-preview tool="resize" />

        @include('livewire.tools._process', ['isGuest' => $isGuest])
    </div>
</div>
