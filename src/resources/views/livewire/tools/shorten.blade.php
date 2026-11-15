@php
    $limits = $this->limits;
    // Alias dihitung per akun, jadi butuh login (sama dengan CreateShortLink).
    $canAlias = ! $this->tier->isGuest() && data_get($limits, 'custom_alias_max') !== 0;
    $canExpiry = (bool) data_get($limits, 'link_custom_expiry');
@endphp

<div class="mx-auto max-w-3xl">
    <header class="mb-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Perpendek Tautan</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Buat tautan pendek yang mudah dibagikan.</p>
            </div>
            <livewire:quota-badge metric="links" />
        </div>
    </header>

    @error('quota')
        <div class="mb-4 flex flex-col gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 sm:flex-row sm:items-center dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200" role="alert">
            <span class="flex-1">{{ $message }}</span>
            <a href="{{ route('pricing') }}" class="btn-sm shrink-0 rounded-lg bg-rose-600 px-3 py-1.5 font-semibold text-white hover:bg-rose-700">Tingkatkan</a>
        </div>
    @enderror

    <div class="card p-6">
        <form wire:submit="shorten" class="space-y-4">
            <div>
                <label for="targetUrl" class="label">Tautan tujuan</label>
                <input id="targetUrl" type="url" wire:model="targetUrl" placeholder="https://contoh.com/halaman-panjang"
                    class="input @error('targetUrl') border-rose-400 @enderror" required>
                @error('targetUrl') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            @if($canAlias)
                <div>
                    <label for="alias" class="label">Alias kustom <span class="font-normal text-slate-500 dark:text-slate-400">(opsional)</span></label>
                    <div class="flex items-center gap-2">
                        <span class="shrink-0 text-sm text-slate-500 dark:text-slate-400">{{ url('/s') }}/</span>
                        <input id="alias" type="text" wire:model="alias" placeholder="promo-ku"
                            class="input @error('alias') border-rose-400 @enderror">
                    </div>
                    @error('alias') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            @endif

            @if($canExpiry)
                <div>
                    <label for="expiresAt" class="label">Kedaluwarsa <span class="font-normal text-slate-500 dark:text-slate-400">(opsional)</span></label>
                    <input id="expiresAt" type="datetime-local" wire:model="expiresAt"
                        class="input @error('expiresAt') border-rose-400 @enderror">
                    @error('expiresAt') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            @endif

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
        <div class="card mt-6 p-6" x-data="{ copied: false }">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Tautan pendekmu siap</p>
            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center">
                <input type="text" readonly value="{{ $result['short_url'] }}"
                    class="input font-medium text-brand-700 dark:text-brand-300" aria-label="Tautan pendek">
                <button type="button" @click="navigator.clipboard.writeText(@js($result['short_url'])); copied = true; setTimeout(() => copied = false, 1500)"
                    class="btn-secondary shrink-0">
                    <span x-show="!copied">Salin</span>
                    <span x-show="copied" x-cloak class="text-emerald-700 dark:text-emerald-400">Tersalin!</span>
                </button>
                <a href="{{ route('tools.qr', ['text' => $result['short_url']]) }}" class="btn-ghost shrink-0">Buat QR</a>
            </div>
            @if($result['expires_at'] ?? null)
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Kedaluwarsa: {{ $result['expires_at'] }}</p>
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
