@php
    $limits = $this->limits;
    // Alias kustom = fitur berbayar tool shorten (sama dengan CreateShortLink).
    $canAlias = app(\App\Services\Tools\ToolCatalog::class)->canUseFeature('shorten', 'custom_alias', $this->tier);
    $canExpiry = (bool) data_get($limits, 'link_custom_expiry');
@endphp

<div class="mx-auto max-w-3xl">
    <header class="mb-6">
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="eyebrow">Tautan</p>
                <h1 class="page-title">Perpendek Tautan</h1>
                <p class="mt-1 text-sm text-muted">Buat tautan pendek yang mudah dibagikan.</p>
            </div>
            <livewire:quota-badge metric="links" />
        </div>
    </header>

    @error('quota')
        <div class="mb-4 flex flex-col gap-3 rounded-xl border border-danger/40 bg-danger/10 px-4 py-3 text-sm text-danger sm:flex-row sm:items-center" role="alert">
            <span class="flex-1">{{ $message }}</span>
            <a href="{{ route('pricing') }}" class="btn-sm shrink-0 rounded-lg bg-danger px-3 py-1.5 font-semibold text-white hover:bg-danger/90">Tingkatkan</a>
        </div>
    @enderror

    <div class="glass-strong p-6">
        <form wire:submit="shorten" class="space-y-4">
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
    </div>

    {{-- Hasil --}}
    @if($result ?? null)
        <div class="glass-strong mt-6 p-6" x-data="{ copied: false }">
            <p class="text-sm font-medium text-muted">Tautan pendekmu siap</p>
            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center">
                <input type="text" readonly value="{{ $result['short_url'] }}"
                    class="input font-medium text-link" aria-label="Tautan pendek">
                <button type="button" @click="navigator.clipboard.writeText(@js($result['short_url'])); copied = true; setTimeout(() => copied = false, 1500)"
                    class="btn-secondary shrink-0">
                    <span x-show="!copied">Salin</span>
                    <span x-show="copied" x-cloak class="text-ok">Tersalin!</span>
                </button>
                <a href="{{ route('tools.qr', ['text' => $result['short_url']]) }}" class="btn-ghost shrink-0">Buat QR</a>
            </div>
            @if($result['expires_at'] ?? null)
                <p class="mt-3 text-xs text-muted">Kedaluwarsa: {{ $result['expires_at'] }}</p>
            @endif

            @if($this->tier->isGuest())
                <x-save-result-cta class="mt-5" />
            @endif
        </div>
    @endif

    @unless($this->tier->isPaid())
        <x-upgrade-cta class="mt-6" reason="Pro mendapat link tanpa batas, alias kustom, dan proteksi password." />
    @endunless
</div>
