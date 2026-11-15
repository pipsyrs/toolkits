@php
    use App\Services\Tier\Entitlements;

    $limits = $this->limits;
    $maxMb = Entitlements::uploadLimit($limits, 'max_file_mb');
    $accept = $this->accept ?: 'application/pdf';
    $isGuest = $this->isGuest;
@endphp

<div class="mx-auto max-w-3xl">
    <header class="mb-6">
        <p class="eyebrow">PDF</p>
        <h1 class="page-title">Pisah PDF</h1>
        <p class="mt-1 text-sm text-muted">Ambil halaman tertentu atau pecah setiap halaman jadi file terpisah.</p>
    </header>

    <div class="glass-strong space-y-5 p-6">
        <x-file-dropzone :accept="$accept" :max-files="1" :max-mb="$maxMb" />

        <fieldset>
            <legend class="label">Mode</legend>
            <div class="flex flex-col gap-2 sm:flex-row">
                <label class="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border border-line px-3 py-2.5 text-sm has-[:checked]:border-line-strong has-[:checked]:bg-sunken">
                    <input type="radio" wire:model.live="mode" value="extract" class="text-ok focus:ring-fg">
                    Ambil rentang halaman
                </label>
                <label class="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border border-line px-3 py-2.5 text-sm has-[:checked]:border-line-strong has-[:checked]:bg-sunken">
                    <input type="radio" wire:model.live="mode" value="each" class="text-ok focus:ring-fg">
                    Pecah tiap halaman
                </label>
            </div>
        </fieldset>

        @if(($mode ?? 'extract') === 'extract')
            <div>
                <label for="ranges" class="label">Rentang halaman</label>
                <input id="ranges" type="text" wire:model="ranges" placeholder="1-3,5,8-10"
                    class="input @error('ranges') border-danger @enderror" inputmode="numeric">
                <p class="mt-1.5 text-xs text-muted">Pisahkan dengan koma. Contoh: <code>1-3,5,8-10</code></p>
                @error('ranges') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        @endif

        <x-preview-panel title="Pilih halaman" x-data="pdfSplit" x-on:files-changed.window="setFiles($event.detail.files)"
            x-show="loading || error || pages.length">
            <p x-show="total" class="text-sm text-muted" aria-live="polite" x-text="summary()"></p>
            <p x-show="extract && selection.outside" class="field-error" x-text="`Ada nomor halaman di luar 1–${total}; halaman itu akan membuat proses gagal.`"></p>
            <x-pdf-pages label="Halaman PDF" />
        </x-preview-panel>

        @include('livewire.tools._process', ['isGuest' => $isGuest])
    </div>
</div>
