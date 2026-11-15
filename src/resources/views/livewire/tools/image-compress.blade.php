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
        <p class="eyebrow">Gambar</p>
        <h1 class="page-title">Kompres Gambar</h1>
        <p class="mt-1 text-sm text-muted">Perkecil ukuran gambar tanpa banyak kehilangan kualitas.</p>
    </header>

    <div class="glass-strong space-y-5 p-6">
        <x-file-dropzone :accept="$accept" :max-files="$maxFiles" :max-mb="$maxMb" multiple />

        <fieldset>
            <legend class="label">Tingkat kompresi</legend>
            <div class="grid gap-2 sm:grid-cols-3">
                @foreach($levels as $val => $label)
                    <label class="cursor-pointer rounded-lg border border-line px-3 py-2.5 text-sm has-[:checked]:border-line-strong has-[:checked]:bg-sunken has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-fg">
                        <input type="radio" wire:model="level" value="{{ $val }}" class="sr-only">
                        <span class="font-medium text-fg">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <x-image-live-preview tool="compress" />

        @include('livewire.tools._process', ['isGuest' => $isGuest])
    </div>
</div>
