{{--
    Tombol + dialog konfirmasi (pengganti wire:confirm). Atribut lain diteruskan ke tombol pemicu.
    <x-confirm-button action="delete('{{ $id }}')" title="Hapus link?" message="Tidak bisa dibatalkan." confirm="Hapus" class="btn-danger-soft btn-sm">Hapus</x-confirm-button>
--}}
@props([
    'action',
    'title',
    'message' => null,
    'confirm' => 'Ya, lanjutkan',
    'cancel' => 'Batal',
    'variant' => 'danger',
])

<div x-data="{ open: false }" x-id="['confirm-title', 'confirm-message']" class="contents">
    <button type="button" {{ $attributes }} @click="open = true" aria-haspopup="dialog">{{ $slot }}</button>

    {{-- Dipindah ke body agar tidak terpotong tabel/modal; Esc dihentikan di sini supaya modal induk tidak ikut tertutup. --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak x-trap.noscroll="open" @keydown.escape.stop.prevent="open = false"
            class="fixed inset-0 z-[60] flex items-center justify-center p-4"
            role="alertdialog" aria-modal="true" :aria-labelledby="$id('confirm-title')" @if($message) :aria-describedby="$id('confirm-message')" @endif>
            <div class="absolute inset-0 bg-ink/50 backdrop-blur-sm" @click="open = false"
                x-show="open" x-transition.opacity.duration.150ms></div>
            <div class="relative w-full max-w-sm rounded-2xl border border-line bg-surface p-6 shadow-xl"
                x-show="open"
                x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="translate-y-2 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
                x-transition:leave="transition duration-100 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <h2 :id="$id('confirm-title')" class="text-lg font-semibold text-fg">{{ $title }}</h2>
                @if($message)
                    <p :id="$id('confirm-message')" class="mt-1.5 text-sm text-muted">{{ $message }}</p>
                @endif
                <div class="mt-6 flex justify-end gap-2">
                    {{-- Fokus awal di "Batal" agar Enter tidak langsung menjalankan aksi. --}}
                    <button type="button" class="btn-secondary" @click="open = false" x-init="$watch('open', v => v && $nextTick(() => $el.focus()))">{{ $cancel }}</button>
                    <button type="button" class="{{ $variant === 'danger' ? 'btn-danger' : 'btn-primary' }}"
                        wire:click="{{ $action }}" @click="open = false">{{ $confirm }}</button>
                </div>
            </div>
        </div>
    </template>
</div>
