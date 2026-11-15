{{--
    Modal dashboard untuk form CRUD dan dialog aksi.
    - wire:model="formOpen": buka/tutup mengikuti properti boolean Livewire (lihat WithFormModal).
    - Tanpa wire:model: memakai variabel Alpine `open` dari x-data induk.
    - submit="save": isi dibungkus <form wire:submit>, dengan footer Batal + Simpan bawaan.
    Slot: default (isi), footer (opsional, menggantikan footer bawaan). Error "form" tampil di atas isi.

    <x-dashboard.modal wire:model="formOpen" :title="$editingId ? 'Edit tipe' : 'Tambah tipe'" submit="save">...</x-dashboard.modal>
--}}
@props([
    'title',
    'description' => null,
    'size' => 'md',
    'submit' => null,
    'submitLabel' => 'Simpan',
    'cancelLabel' => 'Batal',
])

@php
    $model = $attributes->wire('model')->value();
    $width = match ($size) {
        'sm' => 'sm:max-w-sm',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-3xl',
        default => 'sm:max-w-md',
    };
    $body = 'min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4 sm:px-6';
    $footerClass = 'flex flex-wrap justify-end gap-2 border-t border-line px-5 py-4 sm:px-6';
@endphp

<div @if($model) x-data="{ open: $wire.entangle(@js($model)) }" @endif x-id="['modal-title', 'modal-desc']"
    {{ $attributes->whereDoesntStartWith('wire:model')->class('contents') }}>
    <div x-show="open" x-cloak @keydown.escape.window="open && (open = false)"
        class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4"
        role="dialog" aria-modal="true" :aria-labelledby="$id('modal-title')" @if($description) :aria-describedby="$id('modal-desc')" @endif>
        <div class="absolute inset-0 bg-ink/50 backdrop-blur-sm" @click="open = false" aria-hidden="true"
            x-show="open" x-transition.opacity.duration.150ms></div>

        <div x-show="open" x-trap.noscroll="open"
            x-transition:enter="transition duration-150 ease-out motion-reduce:transition-none" x-transition:enter-start="translate-y-2 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition duration-100 ease-in motion-reduce:transition-none" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="relative flex max-h-[92dvh] w-full flex-col rounded-t-2xl border border-line bg-surface shadow-xl sm:rounded-2xl {{ $width }}">
            <div class="flex items-start justify-between gap-4 px-5 pt-5 sm:px-6 sm:pt-6">
                <div class="min-w-0">
                    <h2 :id="$id('modal-title')" class="font-display text-lg font-bold tracking-tight text-fg">{{ $title }}</h2>
                    @if($description)
                        <p :id="$id('modal-desc')" class="mt-1 text-sm text-muted">{{ $description }}</p>
                    @endif
                </div>
                <button type="button" class="btn-ghost btn-sm -mr-2 -mt-1 shrink-0 px-2" @click="open = false" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            @if($submit)
                <form wire:submit="{{ $submit }}" novalidate class="flex min-h-0 flex-1 flex-col">
                    <div class="{{ $body }}">
                        @error('form')
                            <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">{{ $message }}</div>
                        @enderror
                        {{ $slot }}
                    </div>
                    <div class="{{ $footerClass }}">
                        @isset($footer)
                            {{ $footer }}
                        @else
                            <button type="button" class="btn-secondary" @click="open = false">{{ $cancelLabel }}</button>
                            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="{{ $submit }}">
                                <span wire:loading.remove wire:target="{{ $submit }}">{{ $submitLabel }}</span>
                                <span wire:loading wire:target="{{ $submit }}">Menyimpan…</span>
                            </button>
                        @endisset
                    </div>
                </form>
            @else
                <div @class([$body, 'pb-5 sm:pb-6' => ! isset($footer)])>
                    {{ $slot }}
                </div>
                @isset($footer)
                    <div class="{{ $footerClass }}">{{ $footer }}</div>
                @endisset
            @endif
        </div>
    </div>
</div>
