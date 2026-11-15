@php
    // State: off (tanpa secret) | pending (secret, belum confirm) | on (confirmed). QR/secret hanya saat konfirmasi sandi masih baru.
    $tfConfirmedAt = $user?->two_factor_confirmed_at;
    $tfState = $tfConfirmedAt ? 'on' : ($user?->two_factor_secret ? 'pending' : 'off');
    $tfRecent = \App\Support\PasswordConfirmation::recent();
    $tfStatus = session('status');
    $tfShowCodes = $tfState === 'on' && in_array($tfStatus, ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true);
    $tfConfirmUrl = $hasPassword ? route('password.confirm') : route('auth.google.confirm');
    $tfMessages = [
        'two-factor-authentication-enabled' => 'Pindai QR lalu masukkan kode untuk menyelesaikan aktivasi.',
        'two-factor-authentication-confirmed' => 'Autentikasi dua langkah aktif. Simpan kode pemulihan di bawah.',
        'two-factor-authentication-disabled' => 'Autentikasi dua langkah dinonaktifkan.',
        'recovery-codes-generated' => 'Kode pemulihan baru dibuat. Kode lama tidak berlaku lagi.',
    ];
@endphp

<section id="two-factor" class="glass scroll-mt-20 p-6" aria-labelledby="two-factor-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 id="two-factor-title" class="text-lg font-semibold text-fg">Autentikasi dua langkah</h2>
            <p class="mt-1 max-w-prose text-sm text-muted">
                Selain kata sandi atau Google, masuk juga meminta kode 6 digit dari aplikasi authenticator (Google Authenticator, Aegis, 1Password).
            </p>
        </div>
        @if($tfState === 'on')
            <span class="badge bg-ok/10 text-ok">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Aktif
            </span>
        @elseif($tfState === 'pending')
            <span class="badge bg-warn/10 text-warn">Belum selesai</span>
        @else
            <span class="badge bg-fg/6 text-muted">Nonaktif</span>
        @endif
    </div>

    @if(isset($tfMessages[$tfStatus]))
        <div class="mt-4 rounded-lg border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok" role="status">
            {{ $tfMessages[$tfStatus] }}
        </div>
    @endif

    @if($tfState === 'off')
        <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-5">
            @csrf
            <button type="submit" class="btn-primary">Aktifkan autentikasi dua langkah</button>
            <p class="mt-2 text-xs text-muted">Kamu akan diminta konfirmasi identitas terlebih dahulu.</p>
        </form>

    @elseif($tfState === 'pending')
        @if($tfRecent)
            <ol class="mt-5 space-y-5">
                <li class="grid gap-4 sm:grid-cols-[auto_1fr] sm:items-start">
                    <img src="data:image/svg+xml;base64,{{ base64_encode($user->twoFactorQrCodeSvg()) }}" alt="Kode QR autentikasi dua langkah"
                        width="176" height="176" class="h-44 w-44 rounded-lg bg-surface p-2 ring-1 ring-line">
                    <div class="space-y-3 text-sm">
                        <p class="font-medium text-fg">1. Pindai QR dengan aplikasi authenticator.</p>
                        <div x-data="{ copied: false }">
                            <p class="text-muted">Tidak bisa memindai? Masukkan kunci ini secara manual:</p>
                            <div class="mt-1.5 flex items-center gap-2">
                                <code x-ref="secret" class="min-w-0 break-all rounded-md bg-fg/6 px-2 py-1 font-mono text-xs tracking-wider text-fg">{{ \Laravel\Fortify\Fortify::currentEncrypter()->decrypt($user->two_factor_secret) }}</code>
                                <button type="button" class="btn-ghost btn-sm shrink-0"
                                    @click="navigator.clipboard?.writeText($refs.secret.textContent.trim()).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                                    <span x-text="copied ? 'Tersalin' : 'Salin'">Salin</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </li>
                <li>
                    <form method="POST" action="{{ route('two-factor.confirm') }}" class="space-y-3">
                        @csrf
                        <label for="tf-code" class="label">2. Masukkan kode 6 digit dari aplikasi</label>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <input id="tf-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required
                                placeholder="123456"
                                class="input font-mono tracking-[0.3em] sm:w-44 @error('code', 'confirmTwoFactorAuthentication') border-danger @enderror">
                            <button type="submit" class="btn-primary">Konfirmasi &amp; aktifkan</button>
                        </div>
                        @error('code', 'confirmTwoFactorAuthentication') <p class="field-error">{{ $message }}</p> @enderror
                    </form>
                </li>
            </ol>
        @else
            <div class="mt-5 rounded-lg border border-warn/30 bg-warn/10 px-4 py-3 text-sm text-warn">
                Penyiapan belum selesai. Konfirmasi identitas untuk menampilkan kode QR.
            </div>
            <a href="{{ $tfConfirmUrl }}" class="btn-primary mt-4">Konfirmasi {{ $hasPassword ? 'kata sandi' : 'dengan Google' }} untuk melanjutkan</a>
        @endif

        <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-4">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-ghost btn-sm">Batalkan penyiapan</button>
        </form>

    @else
        <p class="mt-3 text-sm text-muted">
            Aktif sejak {{ $tfConfirmedAt->translatedFormat('d M Y, H:i') }}.
        </p>

        {{-- Kode pemulihan --}}
        <div class="mt-5 rounded-xl border border-line p-4"
            x-data="{
                codes: @js($tfShowCodes ? $user->recoveryCodes() : []),
                loading: false,
                error: '',
                copied: false,
                async load() {
                    this.loading = true; this.error = '';
                    try {
                        const res = await fetch(@js(route('two-factor.recovery-codes')), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                        if (res.status === 423) { window.location = @js($tfConfirmUrl); return; }
                        if (! res.ok) throw new Error();
                        this.codes = await res.json();
                    } catch (e) { this.error = 'Gagal memuat kode pemulihan. Coba lagi.'; }
                    this.loading = false;
                },
                copy() { navigator.clipboard?.writeText(this.codes.join('\n')).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000) }); },
                download() {
                    const url = URL.createObjectURL(new Blob([this.codes.join('\n') + '\n'], { type: 'text/plain' }));
                    const a = Object.assign(document.createElement('a'), { href: url, download: 'toolkits-recovery-codes.txt' });
                    a.click(); URL.revokeObjectURL(url);
                },
            }">
            <h3 class="text-sm font-semibold text-fg">Kode pemulihan</h3>
            <p class="mt-1 text-sm text-muted">Pakai salah satu kode bila perangkat authenticator hilang. Tiap kode hanya berlaku sekali.</p>

            <template x-if="codes.length">
                <div class="mt-3">
                    <ul class="grid grid-cols-2 gap-2 rounded-lg bg-fg/6 p-3 font-mono text-sm text-fg" aria-label="Daftar kode pemulihan">
                        <template x-for="code in codes" :key="code">
                            <li x-text="code" class="select-all"></li>
                        </template>
                    </ul>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" class="btn-secondary btn-sm" @click="copy()"><span x-text="copied ? 'Tersalin' : 'Salin'"></span></button>
                        <button type="button" class="btn-secondary btn-sm" @click="download()">Unduh .txt</button>
                        <button type="button" class="btn-ghost btn-sm" @click="codes = []">Sembunyikan</button>
                    </div>
                </div>
            </template>

            <div class="mt-3 flex flex-wrap items-center gap-2" x-show="! codes.length">
                <button type="button" class="btn-secondary btn-sm" @click="load()" :disabled="loading">
                    <span x-text="loading ? 'Memuat…' : 'Tampilkan kode pemulihan'">Tampilkan kode pemulihan</span>
                </button>
            </div>
            <p x-show="error" x-text="error" x-cloak class="field-error" role="alert"></p>

            <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}" class="mt-3">
                @csrf
                <button type="submit" class="btn-ghost btn-sm">Buat ulang kode pemulihan</button>
            </form>
        </div>

        {{-- Nonaktifkan dengan konfirmasi inline --}}
        <div class="mt-5" x-data="{ confirming: false }">
            <button type="button" x-show="! confirming" @click="confirming = true; $nextTick(() => $refs.cancel.focus())"
                class="btn-sm rounded-lg px-3 py-1.5 font-semibold text-danger hover:bg-danger/10">
                Nonaktifkan autentikasi dua langkah
            </button>
            <div x-show="confirming" x-cloak class="rounded-xl border border-danger/30 bg-danger/10 p-4" role="alertdialog" aria-labelledby="tf-disable-title">
                <p id="tf-disable-title" class="text-sm font-medium text-danger">Nonaktifkan? Akunmu hanya dilindungi kata sandi/Google, dan sesi di perangkat lain akan dikeluarkan.</p>
                <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-3 flex flex-wrap gap-2">
                    @csrf
                    @method('DELETE')
                    <button type="button" x-ref="cancel" class="btn-secondary btn-sm" @click="confirming = false">Batal</button>
                    <button type="submit" class="btn-danger btn-sm">Ya, nonaktifkan</button>
                </form>
            </div>
        </div>
    @endif
</section>
