@props([
    'accept' => '',
    'maxFiles' => 1,
    'maxMb' => 5,
    'multiple' => false,
    'sortable' => false,
])

{{-- Pemilih file: validasi cepat di browser, lalu diunggah ke properti Livewire "files" saat event "submit-files".
     Setiap perubahan daftar dikirim lewat event "files-changed" untuk panel pratinjau. --}}
<div
    x-data="{
        files: [],
        error: '',
        maxFiles: {{ (int) $maxFiles }},
        maxBytes: {{ (int) $maxMb }} * 1024 * 1024,
        dragging: false,
        progress: null,
        changed() {
            this.$dispatch('files-changed', { files: [...Alpine.raw(this.files)] });
        },
        clear() {
            Alpine.raw(this.files).forEach((f) => this.$releasePdf(f));
            this.files = [];
            this.changed();
        },
        setBusy(busy) {
            this.progress = busy ? 0 : null;
            this.$dispatch('upload-state', { busy });
        },
        submit() {
            if (this.progress !== null) return;
            if (!this.files.length) { this.$wire.files = []; this.$wire.process(); return; }
            this.error = '';
            this.setBusy(true);
            // Raw File objects: a reactive proxy cannot be appended to FormData.
            this.$wire.$uploadMultiple('files', Alpine.raw(this.files),
                () => { this.setBusy(false); this.$wire.process(); },
                () => { this.setBusy(false); this.error = 'Upload gagal. Periksa ukuran file dan koneksi, lalu coba lagi.'; },
                (e) => { this.progress = e.detail.progress; },
                () => { this.setBusy(false); },
                false,
            );
        },
        add(list) {
            this.error = '';
            let incoming = Array.from(list);
            for (const f of incoming) {
                if (f.size > this.maxBytes) { this.error = `“${f.name}” melebihi {{ $maxMb }} MB.`; continue; }
                if (this.files.length >= this.maxFiles) { this.error = `Maksimal {{ $maxFiles }} file.`; break; }
                this.files.push(f);
            }
            this.changed();
        },
        remove(i) {
            this.$releasePdf(Alpine.raw(this.files)[i]);
            this.files.splice(i, 1);
            this.error = '';
            this.changed();
        },
        move(i, d) {
            const j = i + d;
            if (j < 0 || j >= this.files.length) return;
            [this.files[i], this.files[j]] = [this.files[j], this.files[i]];
            this.changed();
        },
    }"
    x-on:submit-files.window="submit()"
    x-on:files-submitted.window="clear()"
    class="space-y-3"
>
    <label
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="dragging = false; add($event.dataTransfer.files)"
        :class="dragging ? 'border-tool bg-tool/10' : 'border-line'"
        class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-10 text-center transition hover:border-tool bg-sunken/40"
    >
        <svg class="h-8 w-8 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.9A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
        <span class="text-sm font-medium text-fg">Pilih file atau tarik ke sini</span>
        <span class="text-xs text-muted">Maks {{ $maxFiles }} file, {{ $maxMb }} MB / file</span>
        <input type="file"
            @if($multiple) multiple @endif
            @if($accept) accept="{{ $accept }}" @endif
            class="sr-only"
            @change="add($event.target.files); $event.target.value = ''">
    </label>

    <p x-show="error" x-cloak class="field-error" role="alert" x-text="error"></p>

    <div x-show="progress !== null" x-cloak class="space-y-1" role="status">
        <div class="h-2 overflow-hidden rounded-full bg-sunken">
            <div class="h-full rounded-full bg-tool-deep transition-all motion-reduce:transition-none" :style="`width: ${progress ?? 0}%`"></div>
        </div>
        <p class="text-xs text-muted" x-text="`Mengunggah… ${progress ?? 0}%`"></p>
    </div>

    <ul x-show="files.length" x-cloak class="space-y-2" aria-label="File terpilih" data-no-save>
        <template x-for="(f, i) in files" :key="$fileKey(f)">
            <li class="flex items-center gap-3 rounded-lg border border-line bg-surface px-3 py-2 text-sm" x-data="fileThumb(f)">
                <span class="grid h-11 w-11 shrink-0 place-items-center overflow-hidden rounded-md border border-line bg-sunken">
                    <img x-show="url" :src="url" alt="" class="h-full w-full object-cover">
                    <svg x-show="!url" class="h-5 w-5 text-muted" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-medium" x-text="f.name"></span>
                    <span class="block text-xs text-muted" x-text="detail ? `${$bytes(f.size)} · ${detail}` : $bytes(f.size)"></span>
                </span>
                @if($sortable)
                    <div class="flex shrink-0 items-center">
                        <button type="button" @click="move(i, -1)" :disabled="i === 0" class="btn-ghost btn-sm px-1.5 disabled:opacity-30" aria-label="Naik">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 15l7-7 7 7"/></svg>
                        </button>
                        <button type="button" @click="move(i, 1)" :disabled="i === files.length - 1" class="btn-ghost btn-sm px-1.5 disabled:opacity-30" aria-label="Turun">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                    </div>
                @endif
                <button type="button" @click="remove(i)" class="btn-ghost btn-sm shrink-0 px-1.5 text-danger" aria-label="Hapus file">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </li>
        </template>
    </ul>
</div>
