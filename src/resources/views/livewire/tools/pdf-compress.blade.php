@php
    use App\Services\Tier\Entitlements;

    $limits = $this->limits;
    $maxMb = Entitlements::uploadLimit($limits, 'max_file_mb');
    $maxFiles = Entitlements::uploadLimit($limits, 'max_files_per_op');
    $accept = $this->accept ?: 'application/pdf';
    $isGuest = $this->isGuest;
    $levels = [
        'light' => ['Ringan', 'Gambar 200 dpi, kualitas tinggi. Cocok untuk dicetak.'],
        'recommended' => ['Disarankan', 'Gambar 150 dpi. Seimbang untuk layar, email, dan chat.'],
        'strong' => ['Kuat', 'Gambar 96 dpi. Paling kecil, gambar bisa tampak buram.'],
    ];
@endphp

<div class="mx-auto max-w-3xl">
    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Kompres PDF</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Perkecil ukuran file PDF.</p>
    </header>

    <div class="card space-y-5 p-6">
        <x-file-dropzone :accept="$accept" :max-files="$maxFiles" :max-mb="$maxMb" />

        <fieldset>
            <legend class="label">Tingkat kompresi</legend>
            <div class="grid gap-2 sm:grid-cols-3">
                @foreach($levels as $val => [$label, $hint])
                    <label class="cursor-pointer rounded-lg border border-slate-300 px-3 py-2.5 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:focus-visible]:outline-2 dark:border-slate-700 dark:has-[:checked]:bg-brand-600/10">
                        <input type="radio" wire:model="level" value="{{ $val }}" class="sr-only">
                        <span class="block font-medium text-slate-800 dark:text-slate-100">{{ $label }}</span>
                        <span class="mt-0.5 block text-xs text-muted">{{ $hint }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <x-preview-panel title="Pratinjau file" x-data="pdfPages({ limit: 8, width: 110 })"
            x-on:files-changed.window="setFiles($event.detail.files)" x-show="loading || error || pages.length">
            <p x-show="total" class="text-sm text-muted" x-text="`${total} halaman. Ukuran hasil terlihat setelah diproses, beserta pratinjaunya.`"></p>
            <x-pdf-pages label="Halaman PDF" />
        </x-preview-panel>

        @include('livewire.tools._process', ['isGuest' => $isGuest])
    </div>
</div>
