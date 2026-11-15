@php
    use App\Services\Tier\Entitlements;

    $limits = $this->limits;
    $maxMb = Entitlements::uploadLimit($limits, 'max_file_mb');
    $accept = $this->accept ?: 'application/pdf';
    $isGuest = $this->isGuest;
@endphp

<div class="mx-auto max-w-3xl">
    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Pisah PDF</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Ambil halaman tertentu atau pecah setiap halaman jadi file terpisah.</p>
    </header>

    <div class="card space-y-5 p-6">
        <x-file-dropzone :accept="$accept" :max-files="1" :max-mb="$maxMb" />

        <fieldset>
            <legend class="label">Mode</legend>
            <div class="flex flex-col gap-2 sm:flex-row">
                <label class="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 py-2.5 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:border-slate-700 dark:has-[:checked]:bg-brand-600/10">
                    <input type="radio" wire:model.live="mode" value="extract" class="text-brand-600 focus:ring-brand-500">
                    Ambil rentang halaman
                </label>
                <label class="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 py-2.5 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:border-slate-700 dark:has-[:checked]:bg-brand-600/10">
                    <input type="radio" wire:model.live="mode" value="each" class="text-brand-600 focus:ring-brand-500">
                    Pecah tiap halaman
                </label>
            </div>
        </fieldset>

        @if(($mode ?? 'extract') === 'extract')
            <div>
                <label for="ranges" class="label">Rentang halaman</label>
                <input id="ranges" type="text" wire:model="ranges" placeholder="1-3,5,8-10"
                    class="input @error('ranges') border-rose-400 @enderror" inputmode="numeric">
                <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Pisahkan dengan koma. Contoh: <code>1-3,5,8-10</code></p>
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
