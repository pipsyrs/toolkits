@php
    use App\Actions\Admin\ManagePaymentMethod;
    use App\Models\PaymentMethod;

    $iconBtn = 'grid h-8 w-8 place-items-center rounded-md text-muted transition-colors hover:bg-fg/6 hover:text-fg disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent';
@endphp

<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex flex-col gap-3 animate-rise sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="max-w-prose text-sm text-muted">Metode yang aktif muncul di modal pembayaran, dengan urutan seperti di bawah.</p>
            <p class="mt-1 text-sm text-muted"><span class="font-semibold tabular-nums text-fg">{{ $activeCount }}</span> dari {{ $methods->count() }} metode aktif</p>
        </div>
        <button type="button" class="btn-primary shrink-0" wire:click="openCreate" wire:loading.attr="disabled" wire:target="openCreate" @disabled($available->isEmpty())
            @if($available->isEmpty()) title="Semua payment channel sudah ada di daftar." @endif>
            Tambah metode
        </button>
    </div>

    @if(session('status'))
        <div class="rounded-xl border border-ok/30 bg-ok/10 px-4 py-3 text-sm font-medium text-ok animate-rise" role="status">{{ session('status') }}</div>
    @endif
    @error('list')
        <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger animate-rise" role="alert">{{ $message }}</div>
    @enderror

    <div class="glass animate-rise" wire:loading.class="opacity-60" wire:target="move,toggle,delete">
        @if($methods->isEmpty())
            <div class="px-6 py-14 text-center">
                <p class="font-semibold text-fg">Belum ada metode pembayaran.</p>
                <p class="mt-1 text-sm text-muted">Tambahkan dari daftar <a href="{{ route('admin.payment-channels.index') }}" class="font-medium text-fg underline underline-offset-2">payment channel</a>.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[44rem] text-sm">
                    <thead class="border-b border-line text-left text-xs uppercase tracking-wide text-muted">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium">Metode</th>
                            <th scope="col" class="px-5 py-3 font-medium">Tipe</th>
                            <th scope="col" class="px-5 py-3 font-medium">Status</th>
                            <th scope="col" class="px-5 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach($methods as $method)
                            @php($label = $method->paymentChannel?->fullLabel() ?? $method->channel)
                            <tr wire:key="method-{{ $method->id }}">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-payment-badge :channel="$method->paymentChannel" :logo="PaymentMethod::logoUrl($method->logo_path)" class="w-[4.5rem] shrink-0" />
                                        <div class="min-w-0">
                                            <p id="method-name-{{ $method->id }}" class="truncate font-medium text-fg">{{ $label }}</p>
                                            <p class="font-mono text-[11px] text-muted">{{ $method->paymentChannel?->gateway_code ?? $method->channel }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-muted">{{ $method->type?->name ?? 'Tanpa tipe' }}</td>
                                <td class="px-5 py-3">
                                    <x-dashboard.switch :on="$method->is_active" labelledby="method-name-{{ $method->id }}"
                                        wire:click="toggle('{{ $method->id }}')" wire:loading.attr="disabled" wire:target="toggle('{{ $method->id }}')">
                                        {{ $method->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </x-dashboard.switch>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" class="{{ $iconBtn }}" wire:click="move('{{ $method->id }}', true)" @disabled($loop->first) aria-label="Naikkan {{ $label }}">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                        </button>
                                        <button type="button" class="{{ $iconBtn }}" wire:click="move('{{ $method->id }}', false)" @disabled($loop->last) aria-label="Turunkan {{ $label }}">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <button type="button" class="btn-secondary btn-sm ml-1" wire:click="openEdit('{{ $method->id }}')" aria-label="Edit {{ $label }}">Edit</button>
                                        <x-confirm-button class="{{ $iconBtn }} hover:text-danger" :action="'delete(\''.$method->id.'\')'"
                                            :title="'Hapus metode '.$label.'?'" message="Riwayat transaksi tidak berubah." confirm="Hapus" aria-label="Hapus {{ $label }}">
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

    <x-dashboard.modal wire:model="formOpen" submit="save" :submit-label="$editing ? 'Simpan' : 'Tambah'"
        :title="$editing ? 'Edit metode' : 'Tambah metode'"
        :description="$editing ? ($editing->paymentChannel?->gateway_code ?? $editing->channel).' · channel tidak bisa diubah.' : 'Pilih dari master payment channel. Pastikan channel juga aktif di akun payment gateway sebelum dinyalakan.'">
        @unless($editing)
            <div>
                <label for="method-channel" class="label">Channel</label>
                <select id="method-channel" wire:model="channel" required @class(['input', 'border-danger' => $errors->has('channel')])
                    @error('channel') aria-invalid="true" aria-describedby="method-channel-error" @enderror>
                    <option value="">Pilih channel…</option>
                    @foreach($available as $option)
                        <option value="{{ $option->code }}">{{ $option->fullLabel() }} ({{ $option->kind->label() }})</option>
                    @endforeach
                </select>
                @error('channel') <p id="method-channel-error" class="field-error">{{ $message }}</p> @enderror
            </div>
        @endunless

        <div>
            <label for="method-type" class="label">Tipe</label>
            <select id="method-type" wire:model="typeId" @class(['input', 'border-danger' => $errors->has('type')])
                @error('type') aria-invalid="true" aria-describedby="method-type-error" @enderror>
                <option value="">{{ $editing ? 'Tanpa tipe' : 'Otomatis sesuai jenis channel' }}</option>
                @foreach($types as $type)
                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                @endforeach
            </select>
            @error('type') <p id="method-type-error" class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="method-logo" class="label">Logo <span class="font-normal text-muted">(opsional)</span></label>
            @if($editing?->logo_path)
                <div class="mb-2 flex items-center gap-3 rounded-lg border border-line bg-fg/4 px-3 py-2">
                    <img src="{{ PaymentMethod::logoUrl($editing->logo_path) }}" alt="Logo {{ $editing->paymentChannel?->fullLabel() ?? $editing->channel }} saat ini" width="72" height="28" class="h-7 w-[4.5rem] object-contain">
                    <button type="button" class="btn-ghost btn-sm ml-auto text-danger" wire:click="removeLogo" wire:loading.attr="disabled" wire:target="removeLogo,logo">Hapus logo</button>
                </div>
            @endif
            <input id="method-logo" type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp"
                @class(['input text-sm file:mr-3 file:rounded-md file:border-0 file:bg-fg/6 file:px-3 file:py-1 file:text-sm file:font-medium file:text-fg', 'border-danger' => $errors->has('logo')])
                aria-describedby="method-logo-help @error('logo') method-logo-error @enderror" @error('logo') aria-invalid="true" @enderror>
            <p id="method-logo-help" class="mt-1.5 text-xs text-muted">
                PNG, JPG, atau WebP, maks. {{ ManagePaymentMethod::LOGO_MAX_KB }} KB. Bila ada logo, checkout menampilkan logo sebagai pengganti badge channel.
                <span wire:loading wire:target="logo">Mengunggah…</span>
            </p>
            @error('logo') <p id="method-logo-error" class="field-error">{{ $message }}</p> @enderror
        </div>
    </x-dashboard.modal>
</div>
