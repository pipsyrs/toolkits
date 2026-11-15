@php
    use App\Services\Tier\Entitlements;

    $limits = $this->limits;
    $maxMb = Entitlements::uploadLimit($limits, 'max_file_mb');
    $maxFiles = Entitlements::uploadLimit($limits, 'max_files_per_op');
    $accept = $this->accept ?: 'application/pdf';
    $isGuest = $this->isGuest;
@endphp

<div class="mx-auto max-w-3xl">
    <header class="mb-6">
        <p class="eyebrow">PDF</p>
        <h1 class="page-title">Gabung PDF</h1>
        <p class="mt-1 text-sm text-muted">Satukan beberapa PDF menjadi satu. Atur urutan dengan tombol naik/turun.</p>
    </header>

    <div class="glass-strong space-y-5 p-6">
        <x-file-dropzone :accept="$accept" :max-files="$maxFiles" :max-mb="$maxMb" multiple sortable />

        <x-preview-panel title="Pratinjau hasil gabungan" x-data="pdfPages({ limit: 60, width: 110 })"
            x-on:files-changed.window="setFiles($event.detail.files)" x-show="loading || error || pages.length">
            <p x-show="total" class="text-sm text-muted" x-text="`1 PDF berisi ${total} halaman, sesuai urutan file di atas. Angka di pojok menunjukkan asal file.`"></p>
            <x-pdf-pages label="Halaman hasil gabungan" />
        </x-preview-panel>

        @include('livewire.tools._process', ['isGuest' => $isGuest])
    </div>
</div>
