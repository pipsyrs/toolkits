@php
    use App\Services\Tier\Entitlements;

    $limits = $this->limits;
    $maxMb = Entitlements::uploadLimit($limits, 'max_file_mb');
    $maxFiles = Entitlements::uploadLimit($limits, 'max_files_per_op');
    $accept = $this->accept ?: 'image/jpeg,image/png,image/webp';
    $isGuest = $this->isGuest;
    $levels = ['low' => 'Ringan (kualitas terbaik)', 'medium' => 'Sedang (seimbang)', 'high' => 'Kuat (ukuran terkecil)'];
@endphp

<div class="mx-auto max-w-3xl">
    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Kompres Gambar</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Perkecil ukuran gambar tanpa banyak kehilangan kualitas.</p>
    </header>

    <div class="card space-y-5 p-6">
        <x-file-dropzone :accept="$accept" :max-files="$maxFiles" :max-mb="$maxMb" multiple />

        <fieldset>
            <legend class="label">Tingkat kompresi</legend>
            <div class="grid gap-2 sm:grid-cols-3">
                @foreach($levels as $val => $label)
                    <label class="cursor-pointer rounded-lg border border-slate-300 px-3 py-2.5 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:border-slate-700 dark:has-[:checked]:bg-brand-600/10">
                        <input type="radio" wire:model="level" value="{{ $val }}" class="sr-only">
                        <span class="font-medium text-slate-800 dark:text-slate-100">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <x-image-live-preview tool="compress" />

        @include('livewire.tools._process', ['isGuest' => $isGuest])
    </div>
</div>
