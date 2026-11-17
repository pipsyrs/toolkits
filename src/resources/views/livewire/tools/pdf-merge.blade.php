@php
    use App\Services\Tier\Entitlements;

    $limits = $this->limits;
@endphp

<x-tool-layout slug="pdf-merge" :steps="[
    ['Unggah PDF', 'Pilih minimal dua file PDF.'],
    ['Atur urutan', 'Geser naik/turun, pratinjau halaman mengikuti.'],
    ['Unduh', 'Semua halaman tergabung dalam satu PDF.'],
]">
    <x-slot:meta><x-file-tool-meta :limits="$limits" /></x-slot:meta>

    <x-file-dropzone :accept="$this->accept ?: 'application/pdf'" :max-files="Entitlements::uploadLimit($limits, 'max_files_per_op')" :max-mb="Entitlements::uploadLimit($limits, 'max_file_mb')" multiple sortable />

    <x-preview-panel title="Pratinjau hasil gabungan" x-data="pdfPages({ limit: 60, width: 110 })"
        x-on:files-changed.window="setFiles($event.detail.files)" x-show="loading || error || pages.length">
        <p x-show="total" class="text-sm text-muted" x-text="`1 PDF berisi ${total} halaman, sesuai urutan file di atas. Angka di pojok menunjukkan asal file.`"></p>
        <x-pdf-pages label="Halaman hasil gabungan" />
    </x-preview-panel>

    <x-slot:aside>
        <div class="tool-panel">
            <div class="tool-panel-section">
                <p class="tool-panel-title">Ringkasan</p>
                <p class="text-sm text-muted">File digabung sesuai urutan di daftar. Ubah urutan dengan tombol naik/turun di samping nama file.</p>
            </div>

            @include('livewire.tools._process', ['isGuest' => $this->isGuest])
        </div>
    </x-slot:aside>
</x-tool-layout>
