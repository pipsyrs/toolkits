@php
    $nameMax = \App\Actions\Admin\ManagePaymentMethod::NAME_MAX;
    $iconBtn = 'grid h-8 w-8 place-items-center rounded-md text-muted transition-colors hover:bg-fg/6 hover:text-fg disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent';
@endphp

<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex flex-col gap-1 animate-rise sm:flex-row sm:items-center sm:justify-between">
        <p class="max-w-prose text-sm text-muted">Metode yang aktif muncul di modal pembayaran, dengan urutan seperti di bawah.</p>
        <p class="text-sm text-muted"><span class="font-semibold tabular-nums text-fg">{{ $activeCount }}</span> metode aktif</p>
    </div>

    @if(session('status'))
        <div class="rounded-xl border border-ok/30 bg-ok/10 px-4 py-3 text-sm font-medium text-ok animate-rise" role="status">{{ session('status') }}</div>
    @endif
    @error('methods')
        <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger animate-rise" role="alert">{{ $message }}</div>
    @enderror

    <div class="stagger space-y-6">
        @foreach($kinds as $kind)
            @php($rows = $groups->get($kind->value, collect()))
            <section class="glass p-5 sm:p-6" aria-labelledby="kind-{{ $kind->value }}" wire:key="kind-{{ $kind->value }}">
                <h2 id="kind-{{ $kind->value }}" class="font-display text-lg font-bold tracking-tight text-fg">{{ $kind->label() }}</h2>

                @if($rows->isEmpty())
                    <p class="mt-4 rounded-lg bg-fg/4 px-4 py-6 text-center text-sm text-muted">Belum ada metode {{ $kind->label() }}.</p>
                @else
                    <ul class="mt-3 divide-y divide-line">
                        @foreach($rows as $method)
                            <li wire:key="method-{{ $method->id }}" class="flex flex-wrap items-center gap-3 py-3 sm:flex-nowrap">
                                <x-payment-badge :channel="$method->channel" class="w-[4.5rem]" />

                                <div class="min-w-0 flex-1 basis-40">
                                    <label for="name-{{ $method->id }}" class="sr-only">Nama {{ $method->channel->fullLabel() }}</label>
                                    <input id="name-{{ $method->id }}" type="text" value="{{ $method->name }}" maxlength="{{ $nameMax }}"
                                        wire:change="rename('{{ $method->id }}', $event.target.value)"
                                        class="input py-1.5 text-sm" aria-describedby="code-{{ $method->id }}">
                                    <p id="code-{{ $method->id }}" class="mt-1 font-mono text-[11px] text-muted">{{ $method->channel->code() }} · {{ $method->is_active ? 'Aktif' : 'Nonaktif' }}</p>
                                </div>

                                <div class="flex items-center gap-1">
                                    <button type="button" class="{{ $iconBtn }}" wire:click="move('{{ $method->id }}', true)" @disabled($loop->first)
                                        aria-label="Naikkan {{ $method->name }}">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                    </button>
                                    <button type="button" class="{{ $iconBtn }}" wire:click="move('{{ $method->id }}', false)" @disabled($loop->last)
                                        aria-label="Turunkan {{ $method->name }}">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <x-dashboard.switch :on="$method->is_active" labelledby="name-{{ $method->id }}" class="mx-2"
                                        wire:click="toggle('{{ $method->id }}')" wire:loading.attr="disabled" wire:target="toggle('{{ $method->id }}')">
                                        {{ $method->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </x-dashboard.switch>
                                    <x-confirm-button class="{{ $iconBtn }} hover:text-danger" :action="'delete(\''.$method->id.'\')'"
                                        :title="'Hapus metode '.$method->name.'?'" message="Riwayat transaksi tidak berubah." confirm="Hapus" aria-label="Hapus {{ $method->name }}">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </x-confirm-button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach

        {{-- Tambah dari katalog channel yang didukung --}}
        <section class="glass p-5 sm:p-6" aria-labelledby="add-heading">
            <h2 id="add-heading" class="font-display text-lg font-bold tracking-tight text-fg">Tambah metode</h2>
            <p class="mt-1 max-w-prose text-sm text-muted">Hanya channel yang sudah didukung aplikasi. Pastikan channel juga aktif di akun Xendit sebelum dinyalakan.</p>
            @if($available)
                <form wire:submit="add" class="mt-4 flex flex-col gap-3 sm:flex-row">
                    <label for="new-channel" class="sr-only">Channel</label>
                    <select id="new-channel" wire:model="newChannel" class="input sm:max-w-xs" required>
                        <option value="">Pilih channel…</option>
                        @foreach($available as $channel)
                            <option value="{{ $channel->value }}">{{ $channel->fullLabel() }} ({{ $channel->kind()->label() }})</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="add">Tambah</button>
                </form>
            @else
                <p class="mt-4 rounded-lg bg-fg/4 px-4 py-4 text-sm text-muted">Semua channel yang didukung sudah ada di daftar.</p>
            @endif
        </section>
    </div>
</div>
