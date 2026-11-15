@php
    use App\Services\Tier\Entitlements;

    $limits = $this->limits;
    $maxMb = Entitlements::uploadLimit($limits, 'max_file_mb');
    $maxFiles = Entitlements::uploadLimit($limits, 'max_files_per_op');
    $accept = $this->accept ?: 'image/jpeg,image/png,image/webp';
    $isGuest = $this->isGuest;
@endphp

<div class="mx-auto max-w-3xl">
    <header class="mb-6 flex items-center justify-between gap-3">
        <div>
            <p class="eyebrow">Gambar</p>
            <h1 class="page-title">Konversi Gambar</h1>
            <p class="mt-1 text-sm text-muted">Ubah format gambar ke JPG, PNG, atau WebP.</p>
        </div>
    </header>

    <div class="glass-strong space-y-5 p-6">
        <x-file-dropzone :accept="$accept" :max-files="$maxFiles" :max-mb="$maxMb" multiple />

        <div>
            <label for="targetFormat" class="label">Konversi ke</label>
            <select id="targetFormat" wire:model="targetFormat" class="input sm:max-w-xs">
                <option value="webp">WebP (ukuran kecil)</option>
                <option value="jpg">JPG</option>
                <option value="png">PNG</option>
            </select>
        </div>

        <x-image-live-preview tool="convert" />

        @include('livewire.tools._process', ['isGuest' => $isGuest])
    </div>
</div>
