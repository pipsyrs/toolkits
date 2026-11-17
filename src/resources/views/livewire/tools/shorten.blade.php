@php
    $limits = $this->limits;
    // Alias kustom = fitur berbayar tool shorten (sama dengan CreateShortLink).
    $canAlias = app(\App\Services\Tools\ToolCatalog::class)->canUseFeature('shorten', 'custom_alias', $this->tier);
    $canExpiry = (bool) data_get($limits, 'link_custom_expiry');
@endphp

<x-tool-layout slug="shorten" :steps="[
    ['Tempel tautan', 'Alamat lengkap yang diawali https://.'],
    ['Atur alias', 'Opsional: kode sendiri dan tanggal kedaluwarsa.'],
    ['Bagikan', 'Salin tautan pendek atau jadikan QR code.'],
]">
    <x-slot:meta><livewire:quota-badge metric="links" /></x-slot:meta>

    @error('quota')
        <div class="flex flex-col gap-3 rounded-lg border border-danger/40 bg-danger/10 px-4 py-3 text-sm text-danger sm:flex-row sm:items-center" role="alert">
            <span class="flex-1">{{ $message }}</span>
            <a href="{{ route('pricing') }}" class="btn-sm shrink-0 rounded-lg bg-danger px-3 py-1.5 font-semibold text-white hover:bg-danger/90">Tingkatkan</a>
        </div>
    @enderror

    <form wire:submit="shorten" class="space-y-4 rounded-lg border border-line bg-surface p-5">
        <div>
            <label for="targetUrl" class="label">Tautan tujuan</label>
            <input id="targetUrl" type="url" wire:model="targetUrl" placeholder="https://contoh.com/halaman-panjang"
                class="input @error('targetUrl') border-danger @enderror" required>
            @error('targetUrl') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div @class(['opacity-60' => ! $canAlias])>
            <label for="alias" class="label flex flex-wrap items-center gap-2">
                <span>Alias kustom <span class="font-normal text-muted">(opsional)</span></span>
                @unless($canAlias) <x-plan-badge limit-key="tool_shorten_custom_alias" /> @endunless
            </label>
            <div class="flex items-center gap-2">
                <span class="shrink-0 text-sm text-muted">{{ url('/s') }}/</span>
                <input id="alias" type="text" wire:model="alias" placeholder="promo-ku" @disabled(! $canAlias)
                    @class(['input', 'cursor-not-allowed' => ! $canAlias, 'border-danger' => $errors->has('alias')])>
            </div>
            @if($canAlias)
                @error('alias') <p class="field-error">{{ $message }}</p> @enderror
            @else
                <p class="mt-1 text-xs text-muted">Tersedia di paket berbayar — tentukan sendiri kode tautanmu.</p>
            @endif
        </div>

        <div @class(['opacity-60' => ! $canExpiry])>
            <label for="expiresAt" class="label flex flex-wrap items-center gap-2">
                <span>Kedaluwarsa <span class="font-normal text-muted">(opsional)</span></span>
                @unless($canExpiry) <x-plan-badge limit-key="link_custom_expiry" /> @endunless
            </label>
            <input id="expiresAt" type="datetime-local" wire:model="expiresAt" @disabled(! $canExpiry)
                @class(['input', 'cursor-not-allowed' => ! $canExpiry, 'border-danger' => $errors->has('expiresAt')])>
            @if($canExpiry)
                @error('expiresAt') <p class="field-error">{{ $message }}</p> @enderror
            @else
                <p class="mt-1 text-xs text-muted">Tersedia di paket berbayar — atur kapan tautan kedaluwarsa.</p>
            @endif
        </div>

        {{-- Pratinjau langsung dari isian; alias kosong berarti kode acak. --}}
        <div x-data="{
                host() {
                    try { return new URL($wire.targetUrl).host; } catch { return ''; }
                },
            }" x-show="host()" x-cloak class="rounded-lg border border-line bg-sunken/50 px-4 py-3 text-sm" aria-live="polite">
            <p class="text-xs font-medium text-muted">Pratinjau tautan</p>
            <p class="mt-1 break-all">
                <span class="font-semibold text-fg">{{ url('/s') }}/<span x-text="($wire.alias || '').trim() || 'kode-acak'"></span></span>
                <span class="text-muted" aria-hidden="true">→</span>
                <span class="text-muted" x-text="host()"></span>
            </p>
        </div>

        <button type="submit" class="btn-primary w-full sm:w-auto" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="shorten">Perpendek</span>
            <span wire:loading wire:target="shorten">Memproses…</span>
        </button>
    </form>

    <x-slot:aside>
        {{-- Hasil --}}
        @if($result ?? null)
            <div class="tool-panel space-y-4 p-5" x-data="{ copied: false }">
                <p class="tool-panel-title">Tautan pendekmu siap</p>
                <div class="grid gap-2">
                    <input type="text" readonly value="{{ $result['short_url'] }}"
                        class="input font-medium text-link" aria-label="Tautan pendek">
                    <button type="button" @click="navigator.clipboard.writeText(@js($result['short_url'])); copied = true; setTimeout(() => copied = false, 1500)"
                        class="btn-primary">
                        <span x-show="!copied">Salin</span>
                        <span x-show="copied" x-cloak>Tersalin!</span>
                    </button>
                    <a href="{{ route('tools.qr', ['text' => $result['short_url']]) }}" class="btn-secondary">Buat QR dari tautan ini</a>
                </div>
                @if($result['expires_at'] ?? null)
                    <p class="text-xs text-muted">Kedaluwarsa: {{ $result['expires_at'] }}</p>
                @endif

                @if($this->tier->isGuest())
                    <x-save-result-cta />
                @endif
            </div>
        @else
            <div class="tool-panel space-y-2 p-5">
                <p class="tool-panel-title">Hasil</p>
                <p class="text-sm text-muted">Tautan pendek muncul di sini setelah kamu klik <strong class="text-fg">Perpendek</strong>.</p>
            </div>
        @endif

        @unless($this->tier->isPaid())
            <x-upgrade-cta reason="Pro mendapat link tanpa batas, alias kustom, dan proteksi password." />
        @endunless
    </x-slot:aside>
</x-tool-layout>
