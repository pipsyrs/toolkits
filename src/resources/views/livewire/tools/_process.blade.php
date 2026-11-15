{{-- Blok aksi bersama untuk tool file: kirim, status antrean (polling), unduh hasil. --}}
@php
    $operation = $this->operation;
    $status = $operation?->status;
    $human = fn (int $b) => $b < 1048576 ? max(1, round($b / 1024)) . ' KB' : number_format($b / 1048576, 1, ',', '.') . ' MB';
@endphp

<div class="space-y-3 border-t border-slate-200 pt-5 dark:border-slate-800">
    @foreach(['quota', 'files'] as $key)
        @error($key)
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
                @foreach($errors->get($key) as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        @enderror
    @endforeach

    @if($operation === null)
        <div class="flex flex-wrap items-center gap-3" x-data="{ busy: false }" x-on:upload-state.window="busy = $event.detail.busy">
            <button type="button" class="btn-primary" x-on:click="$dispatch('submit-files')"
                x-bind:disabled="busy" wire:loading.attr="disabled" wire:target="process">
                <span wire:loading.remove wire:target="process" x-text="busy ? 'Mengunggah…' : 'Proses file'">Proses file</span>
                <span wire:loading wire:target="process">Mengirim…</span>
            </button>
        </div>
    @elseif($status->isActive())
        <div wire:poll.1500ms="checkOperation" role="status"
            class="flex items-center gap-3 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800 dark:border-brand-500/30 dark:bg-brand-600/10 dark:text-brand-200">
            <svg class="h-5 w-5 shrink-0 animate-spin motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
            <span class="flex-1">{{ $status->label() }}… Halaman ini akan diperbarui otomatis.</span>
            @if($status === \App\Enums\FileOperationStatus::Pending)
                <x-confirm-button action="cancelOperation" wire:loading.attr="disabled" wire:target="cancelOperation"
                    title="Batalkan proses ini?" message="File yang diunggah akan dihapus." confirm="Ya, batalkan" cancel="Lanjutkan proses" class="btn-secondary btn-sm shrink-0">
                    Batalkan
                </x-confirm-button>
            @endif
        </div>
    @elseif($operation->isDownloadable())
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10" role="status">
            <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">Selesai</p>
            <p class="mt-1 truncate text-sm text-emerald-900 dark:text-emerald-100" title="{{ $operation->output_name }}">
                {{ $operation->output_name }} <span class="text-emerald-700 dark:text-emerald-300">· {{ $human((int) $operation->output_size) }}</span>
            </p>
            <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-300">
                @if($operation->expires_at)
                    Tersedia sampai {{ $operation->expires_at->timezone(config('toolkits.timezone'))->translatedFormat('d M Y, H:i') }} WIB.
                @else
                    Disimpan permanen.
                @endif
            </p>
            <x-result-preview :operation="$operation" :items="$this->previews" :human="$human" class="mt-3" />
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('files.download', $operation) }}" class="btn-primary">Unduh hasil</a>
                <button type="button" wire:click="startOver" class="btn-secondary">Proses file lain</button>
            </div>
        </div>
    @else
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200" role="alert">
            <p class="font-semibold">{{ $status === \App\Enums\FileOperationStatus::Expired ? 'Hasil sudah kedaluwarsa' : 'Proses gagal' }}</p>
            @if($operation->error)
                <p class="mt-1">{{ $operation->error }}</p>
            @endif
            <button type="button" wire:click="startOver" class="btn-secondary mt-3">Coba lagi</button>
        </div>
    @endif

    @if($isGuest ?? false)
        <x-save-result-cta />
    @endif
</div>
