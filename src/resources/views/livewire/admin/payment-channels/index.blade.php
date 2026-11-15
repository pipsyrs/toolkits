@php
    use App\Actions\Admin\ManagePaymentChannel;

    $iconBtn = 'grid h-8 w-8 place-items-center rounded-md text-muted transition-colors hover:bg-fg/6 hover:text-fg disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent';
    $field = fn (string $key) => ['input', 'border-danger' => $errors->has($key)];
@endphp

<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex flex-col gap-3 animate-rise sm:flex-row sm:items-center sm:justify-between">
        <p class="max-w-prose text-sm text-muted">Channel yang bisa ditawarkan sebagai <a href="{{ route('admin.payment-methods.index') }}" class="font-medium text-fg underline underline-offset-2">metode bayar</a>. Kode gateway harus sama dengan kode channel di akun payment gateway.</p>
        <button type="button" class="btn-primary shrink-0" wire:click="openCreate" wire:loading.attr="disabled" wire:target="openCreate">Tambah channel</button>
    </div>

    @if(session('status'))
        <div class="rounded-xl border border-ok/30 bg-ok/10 px-4 py-3 text-sm font-medium text-ok animate-rise" role="status">{{ session('status') }}</div>
    @endif
    @error('list')
        <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger animate-rise" role="alert">{{ $message }}</div>
    @enderror

    <div class="glass animate-rise" wire:loading.class="opacity-60" wire:target="move,delete">
        @if($channels->isEmpty())
            <div class="px-6 py-14 text-center">
                <p class="font-semibold text-fg">Belum ada payment channel.</p>
                <p class="mt-1 text-sm text-muted">Tambahkan channel yang sudah aktif di akun payment gateway.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[44rem] text-sm">
                    <thead class="border-b border-line text-left text-xs uppercase tracking-wide text-muted">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium">Channel</th>
                            <th scope="col" class="px-5 py-3 font-medium">Jenis</th>
                            <th scope="col" class="px-5 py-3 font-medium">Masa berlaku</th>
                            <th scope="col" class="px-5 py-3 font-medium">Dipakai</th>
                            <th scope="col" class="px-5 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach($channels as $channel)
                            <tr wire:key="channel-{{ $channel->id }}">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-payment-badge :channel="$channel" class="w-[4.5rem] shrink-0" />
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-fg">{{ $channel->fullLabel() }}</p>
                                            <p class="font-mono text-[11px] text-muted">{{ $channel->code }} · {{ $channel->gateway_code }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-muted">
                                    {{ $channel->kind->label() }}
                                    @if($channel->needs_mobile_number) <span class="block text-xs">Perlu nomor HP</span> @endif
                                </td>
                                <td class="px-5 py-3 tabular-nums text-muted">{{ $channel->fixed_expiry_minutes ? $channel->fixed_expiry_minutes.' menit' : 'Ikut pengaturan' }}</td>
                                <td class="px-5 py-3 tabular-nums text-muted">{{ $channel->methods_count }} metode</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" class="{{ $iconBtn }}" wire:click="move('{{ $channel->id }}', true)" @disabled($loop->first) aria-label="Naikkan {{ $channel->name }}">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                        </button>
                                        <button type="button" class="{{ $iconBtn }}" wire:click="move('{{ $channel->id }}', false)" @disabled($loop->last) aria-label="Turunkan {{ $channel->name }}">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <button type="button" class="btn-secondary btn-sm ml-1" wire:click="openEdit('{{ $channel->id }}')" aria-label="Edit {{ $channel->name }}">Edit</button>
                                        <x-confirm-button class="{{ $iconBtn }} hover:text-danger" :action="'delete(\''.$channel->id.'\')'"
                                            :title="'Hapus channel '.$channel->name.'?'" message="Hanya bisa bila tidak ada metode bayar yang memakai channel ini. Riwayat transaksi tidak berubah." confirm="Hapus" aria-label="Hapus {{ $channel->name }}">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </x-confirm-button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <x-dashboard.modal wire:model="formOpen" submit="save" size="lg" :submit-label="$editing ? 'Simpan' : 'Tambah'"
        :title="$editing ? 'Edit channel' : 'Tambah channel'"
        :description="$editing ? 'Kode '.$editing->code.' dan jenis '.$editing->kind->label().' tidak bisa diubah.' : 'Kode dan jenis tidak bisa diubah setelah dibuat.'">
        @unless($editing)
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="channel-code" class="label">Kode</label>
                    <input id="channel-code" type="text" wire:model="code" maxlength="{{ ManagePaymentChannel::CODE_MAX }}" placeholder="mis. CIMB" autocomplete="off" required
                        @class(array_merge($field('code'), ['font-mono text-sm uppercase'])) @error('code') aria-invalid="true" aria-describedby="channel-code-error" @enderror>
                    @error('code') <p id="channel-code-error" class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="channel-kind" class="label">Jenis</label>
                    <select id="channel-kind" wire:model.live="kind" required @class($field('kind')) @error('kind') aria-invalid="true" aria-describedby="channel-kind-error" @enderror>
                        <option value="">Pilih jenis…</option>
                        @foreach($kinds as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    @error('kind') <p id="channel-kind-error" class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>
        @endunless

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="channel-gateway" class="label">Kode gateway</label>
                <input id="channel-gateway" type="text" wire:model="gatewayCode" maxlength="{{ ManagePaymentChannel::GATEWAY_CODE_MAX }}" placeholder="mis. CIMB_VIRTUAL_ACCOUNT" autocomplete="off" required
                    @class(array_merge($field('gateway_code'), ['font-mono text-sm uppercase'])) @error('gateway_code') aria-invalid="true" aria-describedby="channel-gateway-error" @enderror>
                @error('gateway_code') <p id="channel-gateway-error" class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="channel-name" class="label">Nama singkat</label>
                <input id="channel-name" type="text" wire:model="name" maxlength="{{ ManagePaymentChannel::NAME_MAX }}" placeholder="mis. CIMB Niaga" required
                    @class($field('name')) @error('name') aria-invalid="true" aria-describedby="channel-name-error" @enderror>
                @error('name') <p id="channel-name-error" class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="channel-color" class="label">Warna badge</label>
                <div class="flex items-center gap-2">
                    <input type="color" wire:model.live="color" class="h-10 w-12 shrink-0 cursor-pointer rounded-md border border-line bg-surface p-1" aria-label="Pilih warna badge">
                    <input id="channel-color" type="text" wire:model.live.debounce.300ms="color" maxlength="7" placeholder="#15181d" autocomplete="off" required
                        @class(array_merge($field('color'), ['font-mono text-sm'])) @error('color') aria-invalid="true" aria-describedby="channel-color-error" @enderror>
                </div>
                @error('color') <p id="channel-color-error" class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="channel-expiry" class="label">Masa berlaku tetap (menit)</label>
                <input id="channel-expiry" type="number" inputmode="numeric" wire:model="fixedExpiryMinutes" min="{{ ManagePaymentChannel::EXPIRY_MIN }}" max="{{ ManagePaymentChannel::EXPIRY_MAX }}" placeholder="Ikut pengaturan"
                    @class($field('fixed_expiry_minutes')) aria-describedby="channel-expiry-help @error('fixed_expiry_minutes') channel-expiry-error @enderror" @error('fixed_expiry_minutes') aria-invalid="true" @enderror>
                <p id="channel-expiry-help" class="mt-1.5 text-xs text-muted">Isi bila penyedia menetapkan masa berlaku sendiri (umumnya e-wallet).</p>
                @error('fixed_expiry_minutes') <p id="channel-expiry-error" class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        @if($kind === \App\Enums\PaymentChannelKind::EWallet->value)
            <label class="flex items-start gap-2 text-sm text-fg">
                <input type="checkbox" wire:model="needsMobileNumber" class="mt-0.5 h-4 w-4 rounded border-line-strong">
                <span>Minta nomor HP saat checkout <span class="block text-xs text-muted">Untuk e-wallet yang mengirim notifikasi ke aplikasi, mis. OVO.</span></span>
            </label>
        @endif
    </x-dashboard.modal>
</div>
