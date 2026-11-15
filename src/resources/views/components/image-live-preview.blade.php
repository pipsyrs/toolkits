@props(['tool'])

{{-- Pratinjau langsung di browser untuk alat gambar; mengikuti opsi di $wire. Perkiraan, bukan hasil akhir. --}}
<x-preview-panel hint="Perkiraan, hasil akhir bisa sedikit berbeda"
    x-data="imageLive({{ Js::from($tool) }})" x-on:files-changed.window="setFiles($event.detail.files)" x-effect="options(); schedule()" x-show="files.length">

    <div x-show="files.length > 1" class="flex gap-2 overflow-x-auto pb-1" role="group" aria-label="Pilih gambar untuk dipratinjau">
        <template x-for="(f, i) in files" :key="$fileKey(f)">
            <button type="button" @click="select(i)" :aria-pressed="index === i" :aria-label="f.name"
                :class="index === i ? 'border-line-strong' : 'border-line opacity-70 hover:opacity-100'"
                class="h-14 w-14 shrink-0 overflow-hidden rounded-md border-2 bg-surface" x-data="fileThumb(f)">
                <img x-show="url" :src="url" alt="" class="h-full w-full object-cover">
            </button>
        </template>
    </div>

    {{-- Canvas, bukan <img>: tidak ada URL ke gambar penuh yang bisa disimpan. --}}
    <div x-show="before" class="space-y-3">
        <div x-show="after" class="relative mx-auto w-fit max-w-full">
            <canvas x-ref="view" x-effect="draw()" class="block h-auto max-w-full rounded-md border border-line bg-surface"
                role="img" aria-label="{{ $tool === 'resize' ? 'Pratinjau gambar setelah diubah ukurannya' : 'Perbandingan gambar asli dan pratinjau hasil' }}"></canvas>
            @unless($tool === 'resize')
                <span class="badge absolute left-2 top-2 bg-ink/75 text-white">Asli</span>
                <span class="badge absolute right-2 top-2 bg-ink/75 text-white">Hasil</span>
            @endunless
        </div>
        @unless($tool === 'resize')
            <div x-show="after" class="flex flex-wrap items-center gap-3">
                <label class="flex flex-1 items-center gap-2 text-xs text-muted">
                    <span class="shrink-0">Bandingkan</span>
                    <input type="range" min="0" max="100" x-model.number="split" class="w-full accent-tool-deep"
                        aria-label="Geser untuk membandingkan gambar asli dan hasil">
                </label>
                <button type="button" @click="toggleZoom()" :aria-pressed="zoom" class="btn-secondary btn-sm"
                    x-text="zoom ? 'Tampilkan utuh' : 'Perbesar 100%'"></button>
            </div>
        @endunless

        <dl class="grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-md bg-surface px-3 py-2">
                <dt class="text-xs text-muted">Asli</dt>
                <dd class="font-semibold tabular-nums" x-text="before && `${$bytes(before.size)} · ${before.width}×${before.height}`"></dd>
            </div>
            <div class="rounded-md bg-surface px-3 py-2" aria-live="polite">
                <dt class="text-xs text-muted">Hasil</dt>
                <dd x-show="busy" class="text-muted">Menghitung…</dd>
                <dd x-show="!busy && after" class="font-semibold tabular-nums" x-text="after && `${$bytes(after.size)} · ${after.width}×${after.height}`"></dd>
                <dd x-show="!busy && after" class="text-xs text-muted" x-text="saving()"></dd>
                <dd x-show="!busy && !after" class="text-muted">—</dd>
            </div>
        </dl>
    </div>

    <p x-show="note" class="text-xs text-muted" x-text="note"></p>
</x-preview-panel>
