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
        <p class="eyebrow">Gambar</p>
        <h1 class="page-title">Ubah Ukuran Gambar</h1>
        <p class="mt-1 text-sm text-muted">Perbesar atau perkecil gambar berdasarkan persen atau dimensi.</p>
    </header>

    <div class="glass-strong space-y-5 p-6">
        <x-file-dropzone :accept="$accept" :max-files="$maxFiles" :max-mb="$maxMb" multiple />

        <fieldset>
            <legend class="label">Metode</legend>
            <div class="flex gap-2">
                <label class="flex flex-1 cursor-pointer items-center justify-center rounded-lg border border-line px-3 py-2 text-sm has-[:checked]:border-line-strong has-[:checked]:bg-sunken has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-fg">
                    <input type="radio" wire:model.live="mode" value="percent" class="sr-only"> Persen
                </label>
                <label class="flex flex-1 cursor-pointer items-center justify-center rounded-lg border border-line px-3 py-2 text-sm has-[:checked]:border-line-strong has-[:checked]:bg-sunken has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-fg">
                    <input type="radio" wire:model.live="mode" value="dimension" class="sr-only"> Dimensi (px)
                </label>
            </div>
        </fieldset>

        @if(($mode ?? 'percent') === 'percent')
            <div>
                <label for="percent" class="label">Skala <span class="font-normal text-muted">(<span x-text="$wire.percent"></span>%)</span></label>
                <input id="percent" type="range" min="1" max="100" step="1" wire:model="percent" class="w-full accent-tool-deep">
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
            <label class="flex items-center gap-2 text-sm text-muted">
                <input type="checkbox" wire:model="keepAspect" class="rounded border-line text-ok focus:ring-fg">
                Pertahankan rasio aspek
            </label>
        @endif

        <x-image-live-preview tool="resize" />

        @include('livewire.tools._process', ['isGuest' => $isGuest])
    </div>
</div>
